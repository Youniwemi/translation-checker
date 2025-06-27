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

        $engine
            ->expects($this->once())
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

        $engine
            ->expects($this->once())
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

        $engine->expects($this->once())->method('callClaude')->willReturn('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from Claude CLI');

        $engine->translate('text', 'prompt');
    }

    public function testTranslateThrowsExceptionOnFalseResponse(): void
    {
        $engine = $this->getMockBuilder(ClaudeEngine::class)
            ->onlyMethods(['callClaude'])
            ->getMock();

        $engine
            ->expects($this->once())
            ->method('callClaude')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from Claude CLI');

        $engine->translate('text', 'prompt');
    }
}
