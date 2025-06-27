<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Orhanerday\OpenAi\OpenAi;

class OpenAIEngine implements TranslationEngineInterface
{
    private OpenAi $openai;
    private string $model;

    /**
     * @param string      $apiKey The OpenAI API key
     * @param string|null $apiUrl Optional custom API URL
     * @param string|null $model  Optional model name (defaults to 'gpt-3.5-turbo')
     */
    public function __construct(string $apiKey, ?string $apiUrl = null, ?string $model = null)
    {
        $config = ['api_key' => $apiKey];
        if ($apiUrl !== null) {
            $config['base_uri'] = $apiUrl;
        }
        
        $this->openai = new OpenAi($config['api_key']);
        if (isset($config['base_uri'])) {
            $this->openai->setBaseURL($config['base_uri']);
        }
        
        $this->model = $model ?? 'gpt-3.5-turbo';
    }

    /**
     * {@inheritdoc}
     */
    public function translate(
        string $original,
        string $context,
        string $targetLocale,
        string $textDomain,
        array $glossary = [],
        bool $interactive = false,
        ?string $systemPrompt = null
    ): string {
        // Build the system prompt if not provided
        if ($systemPrompt === null) {
            $language = Translator::getLanguageName(substr($targetLocale, 0, 2));
            $systemPrompt = sprintf(
                'You are a professional translator specializing in WordPress localization. '
                . 'Translate the following text from English to %s (%s). '
                . 'The context is "%s" and the text domain is "%s". '
                . 'Maintain the original tone and style. '
                . 'Preserve any HTML tags, placeholders (like %%s, %%d), and special characters. '
                . 'Ensure the translation is culturally appropriate and follows WordPress localization best practices.',
                $language,
                $targetLocale,
                $context,
                $textDomain
            );

            // Add glossary terms if provided and locale is French
            if (!empty($glossary) && substr($targetLocale, 0, 2) === 'fr') {
                $systemPrompt .= "\n\nIMPORTANT: Use these specific translations for the following terms:";
                foreach ($glossary as $term => $translation) {
                    $systemPrompt .= "\n- " . $term . ' -> ' . $translation;
                }
            }
        }

        // Build the request
        $request = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $original,
                ],
            ],
            'temperature' => 0.8,
        ];

        $response = $this->openai->chat($request);
        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            if (isset($result['error']['message'])) {
                throw new \Exception('OpenAI API error: ' . $result['error']['message']);
            }
            throw new \Exception('Invalid response from OpenAI API');
        }

        return $result['choices'][0]['message']['content'];
    }

    /**
     * {@inheritdoc}
     */
    public function verifyEngine(): void
    {
        $response = $this->openai->retrieveModel($this->model);
        $result = json_decode($response, true);

        if (!is_array($result)) {
            throw new \Exception('Invalid response from API');
        }

        if (isset($result['error'])) {
            throw new \Exception($result['error']['message'] ?? 'Unknown error');
        }

        if (!isset($result['id'])) {
            throw new \Exception('Unable to verify model availability');
        }
    }
}