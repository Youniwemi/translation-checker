<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\TranslationEngine;
use Youniwemi\TranslationChecker\ClaudeEngine;
use Youniwemi\TranslationChecker\OpenAIEngine;

class TranslationEngineTest extends TestCase
{
    /**
     * Test that claude() factory method creates a ClaudeEngine instance
     */
    public function testClaudeFactoryMethod(): void
    {
        $engine = TranslationEngine::claude();
        
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }

    /**
     * Test that claude() factory method passes model parameter
     */
    public function testClaudeFactoryMethodWithModel(): void
    {
        $engine = TranslationEngine::claude('opus');
        
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
        
        // We can't directly test the model property since it's private,
        // but the constructor test in ClaudeEngineTest covers this
    }

    /**
     * Test that openai() factory method creates an OpenAIEngine instance
     */
    public function testOpenAIFactoryMethod(): void
    {
        $engine = TranslationEngine::openai('test-api-key');
        
        $this->assertInstanceOf(OpenAIEngine::class, $engine);
    }

    /**
     * Test that openai() factory method passes all parameters
     */
    public function testOpenAIFactoryMethodWithAllParameters(): void
    {
        $engine = TranslationEngine::openai(
            'test-api-key',
            'https://api.example.com',
            'gpt-4'
        );
        
        $this->assertInstanceOf(OpenAIEngine::class, $engine);
    }
}