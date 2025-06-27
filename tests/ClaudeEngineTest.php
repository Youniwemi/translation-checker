<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\ClaudeEngine;

class ClaudeEngineTest extends TestCase
{
    /**
     * @var ClaudeEngine
     */
    private $engine;

    protected function setUp(): void
    {
        parent::setUp();
        // We'll test with a mock API key
        $this->engine = new ClaudeEngine('test-api-key', 'claude-3-haiku-20240307');
    }

    public function testConstructor(): void
    {
        $engine = new ClaudeEngine('test-key');
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }

    public function testConstructorWithCustomModel(): void
    {
        $engine = new ClaudeEngine('test-key', 'claude-3-opus-20240229');
        $this->assertInstanceOf(ClaudeEngine::class, $engine);
    }

    /**
     * Test that translate throws exception without mocking cURL
     * This ensures our error handling works correctly
     */
    public function testTranslateThrowsExceptionWithInvalidKey(): void
    {
        $this->expectException(\RuntimeException::class);
        
        // This will fail because we're using a test API key
        $this->engine->translate('Hello world', 'Translate to French');
    }

    /**
     * Test that verifyEngine throws exception without mocking cURL
     * This ensures our error handling works correctly
     */
    public function testVerifyEngineThrowsExceptionWithInvalidKey(): void
    {
        $this->expectException(\RuntimeException::class);
        
        // This will fail because we're using a test API key
        $this->engine->verifyEngine();
    }
}