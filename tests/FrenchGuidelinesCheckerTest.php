<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\FrenchGuidelinesChecker;

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
        // Mock OpenAI client
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function ($params) {
                    // Verify that glossary terms are included in the prompt
                    return isset($params['model']) &&
                        $params['model'] === 'mymodel' &&
                        isset($params['messages']) &&
                        is_array($params['messages']) &&
                        str_contains(
                            $params['messages'][0]['content'],
                            'archive -> archive or archiver'
                        );
                })
            )
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Veuillez archiver vos documents',
                            ],
                        ],
                    ],
                ])
            );

        $checker = new FrenchGuidelinesChecker($openai, 'mymodel');

        $result = $checker->translate('Please archive your documents');
        if ($result) {
            $this->assertEquals('Veuillez archiver vos documents', $result[0]);
            // no Flags
            $this->assertEquals(null, $result[1]);
        }
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
                "Le terme 'archive' devrait être traduit par 'archive ou archiver'",
            ],
            ['set', 'définir', 0],
            [
                'set up',
                'définir',
                1,
                "Le terme 'set up' devrait être traduit par 'configurer'",
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
        $warnings = $this->checker->glossaryCheck($term, $translation);
        $this->assertCount($count, $warnings);
        if ($message) {
            $this->assertStringContainsString($message, $warnings[0]);
        }
    }

    public function testInteractiveTranslation(): void
    {
        // Mock OpenAI client
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Bonjour le monde',
                            ],
                        ],
                    ],
                ])
            );

        // Create a partial mock of FrenchGuidelinesChecker to mock promptUser
        $checker = $this->getMockBuilder(FrenchGuidelinesChecker::class)
            ->setConstructorArgs([$openai, 'mymodel'])
            ->onlyMethods(['promptUser'])
            ->getMock();

        $checker->setInteractive(true);

        // Set up the promptUser mock to return accepted translation
        $checker
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', null]);

        $result = $checker->translate('Hello world');
        if ($result) {
            $this->assertEquals('Bonjour le monde', $result[0]);
            $this->assertNull($result[1]); // No flags for accepted translation
        } else {
            $this->fail('Translation result should not be null');
        }
    }

    public function testInteractiveTranslationWithReview(): void
    {
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Bonjour le monde',
                            ],
                        ],
                    ],
                ])
            );

        // Create a partial mock of FrenchGuidelinesChecker
        $checker = $this->getMockBuilder(FrenchGuidelinesChecker::class)
            ->setConstructorArgs([$openai, 'mymodel'])
            ->onlyMethods(['promptUser'])
            ->getMock();

        $checker->setInteractive(true);

        // Set up the promptUser mock to return translation with fuzzy flag
        $checker
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', 'fuzzy']);

        $result = $checker->translate('Hello world');
        if ($result) {
            $this->assertEquals('Bonjour le monde', $result[0]);
            $this->assertEquals('fuzzy', $result[1]);
        } else {
            $this->fail('Translation result should not be null');
        }
    }

    public function testInteractiveTranslationWithStop(): void
    {
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Bonjour le monde',
                            ],
                        ],
                    ],
                ])
            );

        // Create a partial mock of FrenchGuidelinesChecker
        $checker = $this->getMockBuilder(FrenchGuidelinesChecker::class)
            ->setConstructorArgs([$openai, 'mymodel'])
            ->onlyMethods(['promptUser'])
            ->getMock();

        $checker->setInteractive(true);

        // Set up the promptUser mock to return stop flag
        $checker
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn([null, 'stop']);

        $result = $checker->translate('Hello world');
        if ($result) {
            $this->assertNull($result[0]);
            $this->assertEquals('stop', $result[1]);
        } else {
            $this->fail('Translation result should not be null');
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
        $this->assertEquals('French', $this->checker->getLanguageName('fr'));
        $this->assertEquals('German', $this->checker->getLanguageName('de'));
        $this->assertEquals('Spanish', $this->checker->getLanguageName('es'));
        $this->assertEquals('Italian', $this->checker->getLanguageName('it'));
        $this->assertEquals(
            'Portuguese',
            $this->checker->getLanguageName('pt')
        );
        $this->assertEquals('Dutch', $this->checker->getLanguageName('nl'));
        $this->assertEquals('Arabic', $this->checker->getLanguageName('ar'));
        $this->assertEquals('Unknown', $this->checker->getLanguageName(''));
    }

    public function testTranslateToGerman(): void
    {
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function ($params) {
                    // Verify German is in the prompt
                    return isset($params['messages'][0]['content']) &&
                        str_contains(
                            $params['messages'][0]['content'],
                            'German'
                        );
                })
            )
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Hallo Welt',
                            ],
                        ],
                    ],
                ])
            );

        $checker = new FrenchGuidelinesChecker($openai, 'gpt-3.5-turbo');
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
}
