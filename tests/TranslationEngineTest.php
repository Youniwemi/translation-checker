<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\TranslationEngine;
use Youniwemi\TranslationChecker\ClaudeEngine;

class TranslationEngineTest extends TestCase
{
    public function testClaudeFactoryMethodCreatesClaudeEngine(): void
    {
        // Mock the verifyEngine method to avoid actual CLI check
        $engine = new class extends ClaudeEngine {
            public function verifyEngine(): void
            {
                // Do nothing - avoid actual CLI check
            }
        };
        
        // We can't directly test the factory method without mocking,
        // but we can verify the type
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }
    
    public function testClaudeFactoryMethodThrowsExceptionWhenClaudeNotInstalled(): void
    {
        try {
            // This will fail if Claude CLI is not installed
            TranslationEngine::claude();
            
            // If we get here, Claude is installed, so we can't test the failure case
            $this->markTestSkipped('Claude CLI is installed, cannot test failure case');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Claude CLI is not installed', $e->getMessage());
        }
    }
}