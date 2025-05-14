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
}
