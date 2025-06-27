<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\FrenchGuidelinesChecker;
use Youniwemi\TranslationChecker\TranslationEngineInterface;
use Youniwemi\TranslationChecker\Translator;

class TranslatorTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&TranslationEngineInterface
     */
    private $mockEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEngine = $this->createMock(TranslationEngineInterface::class);
    }

    protected function tearDown(): void
    {
        unset($this->mockEngine);
        parent::tearDown();
    }

    public function testTranslateWithGlossary(): void
    {
        $this->mockEngine
            ->expects($this->once())
            ->method('translate')
            ->with(
                'Please archive your documents',
                $this->stringContains('archive -> archive or archiver')
            )
            ->willReturn('Veuillez archiver vos documents');

        $translator = new Translator($this->mockEngine);

        $result = $translator->translate(
            'Please archive your documents',
            'fr',
            FrenchGuidelinesChecker::loadGlossary('fr')
        );

        $this->assertNotNull($result);
        $this->assertEquals('Veuillez archiver vos documents', $result[0]);
        $this->assertNull($result[1]);
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

    public function testInteractiveTranslation(): void
    {
        $this->mockEngine
            ->expects($this->once())
            ->method('translate')
            ->willReturn('Bonjour le monde');

        // Create a partial mock of Translator to mock promptUser
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$this->mockEngine, true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return accepted translation
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', null]);

        $result = $translator->translate('Hello world');

        $this->assertNotNull($result);
        $this->assertEquals('Bonjour le monde', $result[0]);
        $this->assertNull($result[1]);
    }

    public function testInteractiveTranslationWithReview(): void
    {
        $this->mockEngine
            ->expects($this->once())
            ->method('translate')
            ->willReturn('Bonjour le monde');

        // Create a partial mock of Translator
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$this->mockEngine, true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return translation with fuzzy flag
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', 'fuzzy']);

        $result = $translator->translate('Hello world');

        $this->assertNotNull($result);
        $this->assertEquals('Bonjour le monde', $result[0]);
        $this->assertEquals('fuzzy', $result[1]);
    }

    public function testInteractiveTranslationWithStop(): void
    {
        $this->mockEngine
            ->expects($this->once())
            ->method('translate')
            ->willReturn('Bonjour le monde');

        // Create a partial mock of Translator
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$this->mockEngine, true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return stop flag
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn([null, 'stop']);

        $result = $translator->translate('Hello world');

        $this->assertNotNull($result);
        $this->assertNull($result[0]);
        $this->assertEquals('stop', $result[1]);
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

    public function testTranslateToGerman(): void
    {
        $this->mockEngine
            ->expects($this->once())
            ->method('translate')
            ->with(
                'Hello world',
                $this->stringContains('German')
            )
            ->willReturn('Hallo Welt');

        $translator = new Translator($this->mockEngine);
        $result = $translator->translate('Hello world', 'de');

        $this->assertNotNull($result);
        $this->assertEquals('Hallo Welt', $result[0]);
        $this->assertNull($result[1]);
    }

}
