<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

class TranslationEngine
{
    /**
     * Creates an OpenAI translation engine.
     *
     * @param string      $apiKey The OpenAI API key
     * @param string|null $apiUrl Optional custom API URL
     * @param string|null $model  Optional model name
     * @return TranslationEngineInterface
     */
    public static function openai(string $apiKey, ?string $apiUrl = null, ?string $model = null): TranslationEngineInterface
    {
        return new OpenAIEngine($apiKey, $apiUrl, $model);
    }

    /**
     * Creates a Claude translation engine.
     *
     * @param string|null $model Optional model name (defaults to 'sonnet')
     * @return TranslationEngineInterface
     */
    public static function claude(?string $model = null): TranslationEngineInterface
    {
        return new ClaudeEngine($model);
    }
}