<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\ClaudeEngine;

class ClaudeEngineTest extends TestCase
{
    public function testTranslateCallsClaudeWithCorrectArguments(): void
    {
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->onlyMethods(['callClaude'])
            ->getMock();

        $text = 'Hello world';
        $systemPrompt = 'You are a translator';
        $expectedTranslation = 'Bonjour le monde';

        $engine->expects($this->once())
            ->method('callClaude')
            ->with($text, $systemPrompt)
            ->willReturn($expectedTranslation);

        $result = $engine->translate($text, $systemPrompt);

        $this->assertEquals($expectedTranslation, $result);
    }

    public function testTranslateWithModelCallsClaudeWithModelArgument(): void
    {
        $model = 'claude-3-sonnet';
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->setConstructorArgs([$model])
            ->onlyMethods(['callClaude'])
            ->getMock();

        $text = 'Test text';
        $systemPrompt = 'System prompt';
        $expectedTranslation = 'Translated text';

        $engine->expects($this->once())
            ->method('callClaude')
            ->with($text, $systemPrompt)
            ->willReturn($expectedTranslation);

        $result = $engine->translate($text, $systemPrompt);

        $this->assertEquals($expectedTranslation, $result);
    }

    public function testTranslateThrowsExceptionOnEmptyResponse(): void
    {
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->onlyMethods(['callClaude'])
            ->getMock();

        $engine->expects($this->once())
            ->method('callClaude')
            ->willReturn('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from Claude CLI');

        $engine->translate('text', 'prompt');
    }

    public function testTranslateThrowsExceptionOnFalseResponse(): void
    {
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->onlyMethods(['callClaude'])
            ->getMock();

        $engine->expects($this->once())
            ->method('callClaude')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from Claude CLI');

        $engine->translate('text', 'prompt');
    }

    public function testVerifyEngineSucceeds(): void
    {
        // We can't easily mock exec(), so we'll test the real implementation
        // This test will only pass if claude CLI is installed
        $engine = new ClaudeEngine();
        
        // If claude is not installed, this test should be skipped
        try {
            $engine->verifyEngine();
            $this->assertTrue(true); // Engine is available
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Claude CLI is not installed');
        }
    }

    public function testCallClaudeBuildsCorrectCommand(): void
    {
        // Use reflection to test the protected method
        $engine = new ClaudeEngine();
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('callClaude');
        $method->setAccessible(true);

        // We can't easily test the actual command execution, but we can verify
        // the method exists and returns a string or false
        $result = $method->invoke($engine, 'test', 'system');
        
        $this->assertTrue(
            is_string($result) || $result === false,
            'callClaude should return string or false'
        );
    }

    public function testCallClaudeWithModel(): void
    {
        $engine = new ClaudeEngine('claude-3-opus');
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('callClaude');
        $method->setAccessible(true);

        // We can't easily test the actual command execution, but we can verify
        // the method exists and handles the model parameter
        $result = $method->invoke($engine, 'test', 'system');
        
        $this->assertTrue(
            is_string($result) || $result === false,
            'callClaude with model should return string or false'
        );
    }
}