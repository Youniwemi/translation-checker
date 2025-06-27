<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Orhanerday\OpenAi\OpenAi;

class TranslationEngine
{
    /**
     * Create and verify an OpenAI engine
     *
     * @param string $apiKey OpenAI API key
     * @param string $model Model name (e.g., 'gpt-3.5-turbo')
     * @param string|null $apiUrl Optional custom API URL
     * @return TranslationEngineInterface Verified engine ready for use
     * @throws \RuntimeException If engine verification fails
     */
    public static function openai(
        string $apiKey,
        string $model,
        ?string $apiUrl = null
    ): TranslationEngineInterface {
        $ai = new OpenAi($apiKey);
        if ($apiUrl) {
            $ai->setBaseURL($apiUrl);
        }

        $engine = new OpenAiEngine($ai, $model);

        // Verify engine before returning
        $engine->verifyEngine();

        return $engine;
    }

    /**
     * Create and verify a Claude engine
     *
     * @param string|null $model Optional Claude model name
     * @return TranslationEngineInterface Verified engine ready for use
     * @throws \RuntimeException If engine verification fails
     */
    public static function claude(?string $model = null): TranslationEngineInterface
    {
        $engine = new ClaudeEngine($model);
        
        // Verify engine before returning
        $engine->verifyEngine();
        
        return $engine;
    }
}
