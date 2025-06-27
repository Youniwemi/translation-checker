<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\OpenAiEngine;

class OpenAiEngineTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\Orhanerday\OpenAi\OpenAi
     */
    private $mockOpenAi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAi = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
    }

    protected function tearDown(): void
    {
        unset($this->mockOpenAi);
        parent::tearDown();
    }

    public function testTranslate(): void
    {
        $this->mockOpenAi
            ->expects($this->once())
            ->method('chat')
            ->with([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Translate to French'],
                    ['role' => 'user', 'content' => 'Hello world'],
                ],
                'temperature' => 0.8,
            ])
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

        $engine = new OpenAiEngine($this->mockOpenAi, 'gpt-3.5-turbo');
        $result = $engine->translate('Hello world', 'Translate to French');

        $this->assertEquals('Bonjour le monde', $result);
    }

    public function testVerifyEngine(): void
    {
        $this->mockOpenAi
            ->expects($this->once())
            ->method('retrieveModel')
            ->with('gpt-3.5-turbo')
            ->willReturn(
                json_encode([
                    'id' => 'gpt-3.5-turbo',
                    'object' => 'model',
                    'created' => 1677649963,
                    'owned_by' => 'openai',
                ])
            );

        $engine = new OpenAiEngine($this->mockOpenAi, 'gpt-3.5-turbo');

        // Should not throw exception
        $engine->verifyEngine();
        $this->assertTrue(true); // Assertion to mark test as having assertions
    }

    public function testVerifyEngineModelNotFound(): void
    {
        $this->mockOpenAi
            ->expects($this->once())
            ->method('retrieveModel')
            ->with('gpt-3.5-turbo')
            ->willReturn(
                json_encode([
                    'error' => [
                        'message' => "The model 'gpt-3.5-turbo' does not exist",
                        'type' => 'invalid_request_error',
                        'code' => 'model_not_found',
                    ],
                ])
            );

        $engine = new OpenAiEngine($this->mockOpenAi, 'gpt-3.5-turbo');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The model 'gpt-3.5-turbo' does not exist");

        $engine->verifyEngine();
    }

    public function testVerifyEngineInvalidKey(): void
    {
        $this->mockOpenAi
            ->expects($this->once())
            ->method('retrieveModel')
            ->with('gpt-3.5-turbo')
            ->willReturn(
                json_encode([
                    'error' => [
                        'message' => 'Incorrect API key provided',
                        'type' => 'invalid_request_error',
                        'code' => 'invalid_api_key',
                    ],
                ])
            );

        $engine = new OpenAiEngine($this->mockOpenAi, 'gpt-3.5-turbo');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect API key provided');

        $engine->verifyEngine();
    }

    public function testVerifyEngineInvalidJson(): void
    {
        $this->mockOpenAi
            ->expects($this->once())
            ->method('retrieveModel')
            ->with('gpt-3.5-turbo')
            ->willReturn('invalid json');

        $engine = new OpenAiEngine($this->mockOpenAi, 'gpt-3.5-turbo');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response from API');

        $engine->verifyEngine();
    }
}
