<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\FrenchGuidelinesChecker;
use Youniwemi\TranslationChecker\Translator;

class FrenchGuidelinesCheckerTest extends TestCase
{
    private FrenchGuidelinesChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FrenchGuidelinesChecker();
    }

    public function testSingleLineTranslation(): void
    {
        $po = <<<PO
            msgid "Hello!"
            msgstr "Bonjour!"
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'Espace insécable manquant avant',
            $result['errors'][0]
        );

        $result = $this->checker->check($po, true);

        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Bonjour !',
            $result['fixed_content']
        );
    }

    public function testMultiLineTranslation(): void
    {
        $po = <<<PO
            msgid ""
            "In just a few steps, you'll meet all e-invoicing regulations effortlessly. "
            "All you need to do is set up your company details and visually customize "
            "your invoice."
            msgstr ""
            "En quelques étapes, vous répondrez sans effort à toutes les réglementations "
            "de facturation électronique. Il vous suffit de configurer les détails de "
            "votre entreprise et de personnaliser visuellement votre facture."
            PO;
        $result = $this->checker->check($po);
        $this->assertCount(0, $result['errors']);
    }

    public function testPunctuationSpacing(): void
    {
        $po = <<<PO
            msgid "Questions and answers"
            msgstr "Questions et réponses!"
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'Espace insécable manquant avant',
            $result['errors'][0]
        );
        $result = $this->checker->check($po, true);

        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Questions et réponses !',
            $result['fixed_content']
        );
    }

    public function testQuotationMarks(): void
    {
        $po = <<<PO
            msgid "He said \"hello\" to everyone"
            msgstr "Il a dit "bonjour" à tout le monde"
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'guillemets français',
            $result['errors'][0]
        );

        $result = $this->checker->check($po, true);

        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Il a dit « bonjour » à tout le monde',
            $result['fixed_content']
        );
    }

    public function testApostrophes(): void
    {
        $po = <<<PO
            msgid "The user's guide"
            msgstr "Le guide de l'utilisateur"
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'apostrophe typographique',
            $result['errors'][0]
        );

        $result = $this->checker->check($po, true);

        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Le guide de l’utilisateur',
            $result['fixed_content']
        );
    }

    public function testNoEllipsisAfterEtc(): void
    {
        $po = <<<PO
            msgid "For example, apples, etc..."
            msgstr "Par exemple, pommes, etc..."
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString(
            'Utiliser le caractère unique pour les points de suspension',
            $result['errors'][0]
        );

        $this->assertStringContainsString(
            'Pas de points de suspension après "etc."',
            $result['errors'][1]
        );

        $result = $this->checker->check($po, true);

        $this->assertCount(2, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Par exemple, pommes, etc.',
            $result['fixed_content']
        );
        $this->assertStringNotContainsString(
            'Par exemple, pommes, etc…',
            $result['fixed_content']
        );
        $this->assertStringNotContainsString(
            'Par exemple, pommes, etc...',
            $result['fixed_content']
        );
    }
    public function testEllipsis(): void
    {
        $po = <<<PO
            msgid "This is a test..."
            msgstr "Ceci est un test..."
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'caractère unique pour les points de suspension',
            $result['errors'][0]
        );

        $result = $this->checker->check($po, true);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Ceci est un test…',
            $result['fixed_content']
        );
    }

    public function testTranslateEmptyStringWithGlossary(): void
    {
        // Mock translation engine
        $engine = $this->createMock(\Youniwemi\TranslationChecker\TranslationEngineInterface::class);
        $engine
            ->expects($this->once())
            ->method('translate')
            ->with(
                'Please archive your documents',
                $this->stringContains('archive -> archive or archiver')
            )
            ->willReturn('Veuillez archiver vos documents');

        $translation = new Translator($engine);
        $checker = new FrenchGuidelinesChecker($translation);

        $result = $checker->translate(
            'Please archive your documents',
            'fr',
            $checker->loadGlossary('fr')
        );

        $this->assertNotNull($result);
        $this->assertEquals('Veuillez archiver vos documents', $result[0]);
        $this->assertNull($result[1]);
    }

    public function testTranslateWithNoConfiguration(): void
    {
        $checker = new FrenchGuidelinesChecker();

        $po = <<<PO
            msgid "Hello world!"
            msgstr ""
            PO;

        $result = $checker->translate($po);

        // Should not modify content when no translation service is configured
        $this->assertNull($result);
    }

    /**
     * Data provider for glossary terms
     *
     * @return array<int, array<int, string|int|null>>
     */
    public static function glossaryData(): array
    {
        return [
            [
                'archive',
                'Compress',
                1,
                "Le terme 'archive' devrait être traduit par 'archive ou archiver' : Compress",
            ],
            ['set', 'définir', 0],
            [
                'set up',
                'définir',
                1,
                "Le terme 'set up' devrait être traduit par 'configurer' : définir",
            ],
            // Should not fail, there is no preset, but only set
            ['preset', 'réglage', 0],
        ];
    }

    /**
     * @dataProvider glossaryData
     */
    public function testGlossaryTermConsistency(
        string $term,
        string $translation,
        int $count,
        ?string $message = null
    ): void {
        $result = $this->checker->glossaryCheck(
            $term,
            $translation,
            FrenchGuidelinesChecker::loadGlossary('fr')
        );
        $this->assertCount($count, $result['warnings']);
        if ($message) {
            $this->assertStringContainsString($message, $result['warnings'][0]);
        }
    }

    public function testDetectLanguageFromFilename(): void
    {
        $this->assertEquals(
            'fr',
            $this->checker->detectLanguageFromFilename('plugin-fr.po')
        );
        $this->assertEquals(
            'fr',
            $this->checker->detectLanguageFromFilename('plugin-fr_FR.po')
        );
        $this->assertEquals(
            'de',
            $this->checker->detectLanguageFromFilename('plugin-de.po')
        );
        $this->assertEquals(
            'de',
            $this->checker->detectLanguageFromFilename('plugin-de_DE.po')
        );
        $this->assertEquals(
            'es',
            $this->checker->detectLanguageFromFilename('plugin-es_ES.po')
        );
        $this->assertEquals(
            'it',
            $this->checker->detectLanguageFromFilename('plugin-it_IT.po')
        );
        $this->assertNull(
            $this->checker->detectLanguageFromFilename('plugin.po')
        );
        $this->assertNull(
            $this->checker->detectLanguageFromFilename('readme.txt')
        );
    }

    public function testGetLanguageName(): void
    {
        $this->assertEquals('French', Translator::getLanguageName('fr'));
        $this->assertEquals('German', Translator::getLanguageName('de'));
        $this->assertEquals('Spanish', Translator::getLanguageName('es'));
        $this->assertEquals('Italian', Translator::getLanguageName('it'));
        $this->assertEquals('Portuguese', Translator::getLanguageName('pt'));
        $this->assertEquals('Dutch', Translator::getLanguageName('nl'));
        $this->assertEquals('Arabic', Translator::getLanguageName('ar'));
        $this->assertEquals('Unknown', Translator::getLanguageName(''));
    }

    public function testPercentageSpacing(): void
    {
        $po = <<<PO
            msgid "The success rate is 50%"
            msgstr "Le taux de réussite est de 50%"
            PO;
        $result = $this->checker->check($po);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString(
            'Espace insécable avant le signe de pourcentage',
            $result['errors'][0]
        );

        $result = $this->checker->check($po, true);

        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey('fixed_content', $result);
        $this->assertIsString($result['fixed_content']);
        $this->assertStringContainsString(
            'Le taux de réussite est de 50' . FrenchGuidelinesChecker::NBSP . '%',
            $result['fixed_content']
        );
    }

    public function testTranslateToGerman(): void
    {
        $engine = $this->createMock(\Youniwemi\TranslationChecker\TranslationEngineInterface::class);
        $engine
            ->expects($this->once())
            ->method('translate')
            ->with(
                'Hello world',
                $this->stringContains('German')
            )
            ->willReturn('Hallo Welt');

        $translator = new Translator($engine);
        $checker = new FrenchGuidelinesChecker($translator);
        $result = $checker->translate('Hello world', 'de');

        $this->assertNotNull($result);
        $this->assertEquals('Hallo Welt', $result[0]);
        $this->assertNull($result[1]);
    }

    public function testSkipTypographyForGerman(): void
    {
        $po = <<<PO
            msgid "Hello!"
            msgstr "Hallo!"
            PO;

        $result = $this->checker->check($po, false, false, 'de');
        $this->assertCount(0, $result['errors']); // No typography errors for German
        $this->assertCount(0, $result['warnings']); // No glossary warnings for German
    }

    public function testGlossaryCommentsAddedToTranslations(): void
    {
        $po = <<<PO
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"
            PO;

        $result = $this->checker->check($po, true); // Use fix=true to apply comments

        // Should have a warning
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString("Le terme 'archive' devrait être traduit par 'archive ou archiver'", $result['warnings'][0]);

        // Should have added a comment to the fixed content
        $this->assertNotNull($result['fixed_content']);
        $this->assertStringContainsString('glossary-review:', $result['fixed_content']);
        $this->assertStringContainsString("'archive' → 'archive ou archiver'", $result['fixed_content']);
    }

    public function testRetranslateGlossaryOnlyProcessesEntriesWithGlossaryComments(): void
    {
        // PO content with one entry having glossary-review comment and one without
        $po = <<<PO
            msgid "Test message without comment"
            msgstr ""

            # glossary-review: 'archive' → 'archive ou archiver'
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"

            msgid "Another untranslated message"
            msgstr ""
            PO;

        // Mock translator that counts how many times translate is called
        $mockTranslator = $this->createMock(Translator::class);
        $mockTranslator->expects($this->once()) // Should only be called once for the glossary-review entry
            ->method('translate')
            ->willReturn(['Veuillez archiver vos documents', null]);

        $checker = new FrenchGuidelinesChecker($mockTranslator);

        // Test with retranslateGlossary=true
        $result = $checker->check($po, false, true, 'fr', true);

        // Should process only the entry with glossary-review comment
        $this->assertIsArray($result);
    }

    public function testNormalTranslationModeIgnoresGlossaryComments(): void
    {
        // PO content with translated entry having glossary-review comment and untranslated without
        $po = <<<PO
            msgid "Test message without comment"
            msgstr ""

            # glossary-review: 'archive' → 'archive ou archiver'
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"
            PO;

        // Mock translator should be called once for the empty translation only
        $mockTranslator = $this->createMock(Translator::class);
        $mockTranslator->expects($this->once()) // Should only be called for empty translation
            ->method('translate')
            ->willReturn(['Message de test traduit', null]);

        $checker = new FrenchGuidelinesChecker($mockTranslator);

        // Test with normal translation mode (retranslateGlossary=false)
        $result = $checker->check($po, false, true, 'fr', false);

        // Should process only untranslated entries
        $this->assertIsArray($result);
    }

    public function testRetranslateGlossaryRemovesGlossaryReviewComments(): void
    {
        // PO content with glossary-review comment
        $po = <<<PO
            # glossary-review: 'archive' → 'archive ou archiver'
            msgid "Please archive your documents"
            msgstr "Veuillez compresser vos documents"
            PO;

        // Mock translator that provides a corrected translation
        $mockTranslator = $this->createMock(Translator::class);
        $mockTranslator->expects($this->once())
            ->method('translate')
            ->willReturn(['Veuillez archiver vos documents', null]);

        $checker = new FrenchGuidelinesChecker($mockTranslator);

        // Test with retranslateGlossary=true and fix=true to get the fixed content
        $result = $checker->check($po, true, true, 'fr', true);

        // Should remove the glossary-review comment from the fixed content
        $this->assertNotNull($result['fixed_content']);
        $this->assertStringNotContainsString('glossary-review:', $result['fixed_content']);
        $this->assertStringContainsString('Veuillez archiver vos documents', $result['fixed_content']);
    }
}
