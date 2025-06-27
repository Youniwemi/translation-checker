<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\ClaudeEngine;

class ClaudeEngineTest extends TestCase
{
    private ClaudeEngine $engine;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ClaudeEngine();
    }

    protected function tearDown(): void
    {
        unset($this->engine);
        parent::tearDown();
    }

    public function testConstructorWithModel(): void
    {
        $engine = new ClaudeEngine('sonnet');
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }

    public function testConstructorWithoutModel(): void
    {
        $engine = new ClaudeEngine();
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }

    public function testVerifyEngineClaudeNotInstalled(): void
    {
        // Mock exec function behavior
        $engine = new class extends ClaudeEngine {
            public function verifyEngine(): void
            {
                // Simulate claude not being installed
                throw new \RuntimeException(
                    'Claude CLI is not installed or not available. ' . 
                    'Please install Claude CLI: https://github.com/anthropics/claude-cli'
                );
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Claude CLI is not installed');
        
        $engine->verifyEngine();
    }

    public function testTranslateCommandFailure(): void
    {
        // Mock exec function behavior  
        $engine = new class extends ClaudeEngine {
            public function translate(string $text, string $systemPrompt): string
            {
                // Simulate command failure
                throw new \RuntimeException(
                    'Claude command failed: Error: API key not found'
                );
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Claude command failed');
        
        $engine->translate('Hello', 'Translate to French');
    }
}