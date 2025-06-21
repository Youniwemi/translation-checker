<?php

declare(strict_types=1);

namespace Youniwemi\TranslationCheckerTests;

use PHPUnit\Framework\TestCase;
use Youniwemi\TranslationChecker\FrenchGuidelinesChecker;
use Youniwemi\TranslationChecker\Translator;

class TranslatorTest extends TestCase
{
    public function testTranslateEmptyStringWithGlossary(): void
    {
        // Mock OpenAI client
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function ($params) {
                    // Verify that glossary terms are included in the prompt
                    return isset($params['model']) &&
                        $params['model'] === 'mymodel' &&
                        isset($params['messages']) &&
                        is_array($params['messages']) &&
                        str_contains(
                            $params['messages'][0]['content'],
                            'archive -> archive or archiver'
                        );
                })
            )
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Veuillez archiver vos documents',
                            ],
                        ],
                    ],
                ])
            );

        $translator = new Translator($openai, 'mymodel');

        $result = $translator->translate(
            'Please archive your documents',
            'fr',
            FrenchGuidelinesChecker::loadGlossary('fr')
        );
        if ($result) {
            $this->assertEquals('Veuillez archiver vos documents', $result[0]);
            // no Flags
            $this->assertEquals(null, $result[1]);
        }
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
        // Mock OpenAI client
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
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

        // Create a partial mock of Translator to mock promptUser
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$openai, 'mymodel', true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return accepted translation
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', null]);

        $result = $translator->translate('Hello world');
        if ($result) {
            $this->assertEquals('Bonjour le monde', $result[0]);
            $this->assertNull($result[1]); // No flags for accepted translation
        } else {
            $this->fail('Translation result should not be null');
        }
    }

    public function testInteractiveTranslationWithReview(): void
    {
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
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

        // Create a partial mock of Translator
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$openai, 'mymodel', true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return translation with fuzzy flag
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn(['Bonjour le monde', 'fuzzy']);

        $result = $translator->translate('Hello world');
        if ($result) {
            $this->assertEquals('Bonjour le monde', $result[0]);
            $this->assertEquals('fuzzy', $result[1]);
        } else {
            $this->fail('Translation result should not be null');
        }
    }

    public function testInteractiveTranslationWithStop(): void
    {
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
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

        // Create a partial mock of Translator
        $translator = $this->getMockBuilder(Translator::class)
            ->setConstructorArgs([$openai, 'mymodel', true])
            ->onlyMethods(['promptUser'])
            ->getMock();

        // Set up the promptUser mock to return stop flag
        $translator
            ->expects($this->once())
            ->method('promptUser')
            ->with('Hello world', 'Bonjour le monde', [])
            ->willReturn([null, 'stop']);

        $result = $translator->translate('Hello world');
        if ($result) {
            $this->assertNull($result[0]);
            $this->assertEquals('stop', $result[1]);
        } else {
            $this->fail('Translation result should not be null');
        }
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
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function ($params) {
                    // Verify German is in the prompt
                    return isset($params['messages'][0]['content']) &&
                        str_contains(
                            $params['messages'][0]['content'],
                            'German'
                        );
                })
            )
            ->willReturn(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Hallo Welt',
                            ],
                        ],
                    ],
                ])
            );

        $translator = new Translator($openai, 'gpt-3.5-turbo');
        $result = $translator->translate('Hello world', 'de');

        $this->assertNotNull($result);
        $this->assertEquals('Hallo Welt', $result[0]);
        $this->assertNull($result[1]);
    }

    public function testVerifyApiCredentials(): void
    {
        // Test successful API verification via model retrieval
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
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

        $translator = new Translator($openai, 'gpt-3.5-turbo');
        $result = $translator->verifyApiCredentials();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function testVerifyApiCredentialsModelNotFound(): void
    {
        // Test model not found
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
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

        $translator = new Translator($openai, 'gpt-3.5-turbo');
        $result = $translator->verifyApiCredentials();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals(
            "The model 'gpt-3.5-turbo' does not exist",
            $result['error']
        );
    }

    public function testVerifyApiCredentialsInvalidKey(): void
    {
        // Test invalid API key
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
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

        $translator = new Translator($openai, 'gpt-3.5-turbo');
        $result = $translator->verifyApiCredentials();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Incorrect API key provided', $result['error']);
    }

    public function testVerifyApiCredentialsInvalidJson(): void
    {
        // Test invalid JSON response
        $openai = $this->createMock(\Orhanerday\OpenAi\OpenAi::class);
        $openai
            ->expects($this->once())
            ->method('retrieveModel')
            ->with('gpt-3.5-turbo')
            ->willReturn('invalid json');

        $translator = new Translator($openai, 'gpt-3.5-turbo');
        $result = $translator->verifyApiCredentials();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid response from API', $result['error']);
    }
}
