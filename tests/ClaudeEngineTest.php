<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\ClaudeEngine;

class ClaudeEngineTest extends TestCase
{
    /**
     * Test that translate method executes claude command with correct parameters and returns translated text
     */
    public function testTranslate(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['sonnet'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        $expectedCommand = [
            'claude',
            '--model',
            'sonnet',
            'Translate the following text from English to French (fr_FR).'
            . ' Context: Theme Name. Text domain: my-theme.'
            . "\n\nOriginal text:\nHello world"
        ];

        // Set up the executeClaudeCommand mock to return translated text
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->with($expectedCommand)
            ->willReturn('Bonjour le monde');

        $result = $engine->translate(
            'Hello world',
            'Theme Name',
            'fr_FR',
            'my-theme'
        );

        $this->assertEquals('Bonjour le monde', $result);
    }

    /**
     * Test that translate method handles custom system prompt
     */
    public function testTranslateWithCustomSystemPrompt(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['opus'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        $customPrompt = 'Please translate this text carefully.';
        $expectedCommand = [
            'claude',
            '--model',
            'opus',
            $customPrompt . "\n\nOriginal text:\nTest text"
        ];

        // Set up the executeClaudeCommand mock
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->with($expectedCommand)
            ->willReturn('Texte de test');

        $result = $engine->translate(
            'Test text',
            'Plugin Name',
            'fr_FR',
            'my-plugin',
            [],
            false,
            $customPrompt
        );

        $this->assertEquals('Texte de test', $result);
    }

    /**
     * Test that translate method handles glossary terms for French
     */
    public function testTranslateWithGlossary(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['sonnet'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        $glossary = [
            'archive' => 'archive ou archiver',
            'set up' => 'configurer'
        ];

        $expectedCommand = [
            'claude',
            '--model',
            'sonnet',
            'Translate the following text from English to French (fr_FR).'
            . ' Context: Plugin Name. Text domain: my-plugin.'
            . "\n\nIMPORTANT: Use these specific translations for the following terms:"
            . "\n- archive -> archive ou archiver"
            . "\n- set up -> configurer"
            . "\n\nOriginal text:\nPlease set up the archive"
        ];

        // Set up the executeClaudeCommand mock
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->with($expectedCommand)
            ->willReturn('Veuillez configurer l\'archive');

        $result = $engine->translate(
            'Please set up the archive',
            'Plugin Name',
            'fr_FR',
            'my-plugin',
            $glossary
        );

        $this->assertEquals('Veuillez configurer l\'archive', $result);
    }

    /**
     * Test that translate throws exception when claude command fails
     */
    public function testTranslateThrowsExceptionOnCommandFailure(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['sonnet'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        // Set up the executeClaudeCommand mock to throw exception
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->willThrowException(new \Exception('Claude command failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Claude command failed');

        $engine->translate(
            'Hello world',
            'Theme Name',
            'fr_FR',
            'my-theme'
        );
    }

    /**
     * Test that verifyEngine properly validates Claude CLI availability
     */
    public function testVerifyEngine(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['sonnet'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        // Set up the executeClaudeCommand mock to return success
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->with(['claude', '--version'])
            ->willReturn('Claude CLI version 1.0.0');

        // This should not throw an exception
        $engine->verifyEngine();
        $this->assertTrue(true); // If we get here, the test passed
    }

    /**
     * Test that verifyEngine throws exception if claude command is not found
     */
    public function testVerifyEngineThrowsExceptionWhenClaudeNotFound(): void
    {
        // Create a partial mock of ClaudeEngine to mock executeClaudeCommand
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs(['sonnet'])
            ->onlyMethods(['executeClaudeCommand'])
            ->getMock();

        // Set up the executeClaudeCommand mock to throw exception
        $engine
            ->expects($this->once())
            ->method('executeClaudeCommand')
            ->with(['claude', '--version'])
            ->willThrowException(new \Exception('claude command not found'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Claude CLI is not installed or not accessible');

        $engine->verifyEngine();
    }
}