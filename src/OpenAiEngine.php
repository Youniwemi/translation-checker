<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Orhanerday\OpenAi\OpenAi;

class OpenAiEngine implements TranslationEngineInterface
{
    private OpenAi $openAi;
    private string $model;

    public function __construct(OpenAi $openAi, string $model)
    {
        $this->openAi = $openAi;
        $this->model = $model;
    }

    public function translate(string $text, string $systemPrompt): string
    {
        $request = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.8,
        ];

        $response = $this->openAi->chat($request);
        if (!is_string($response)) {
            throw new \RuntimeException('Invalid response from OpenAI API');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from OpenAI API');
        }

        if (isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        throw new \RuntimeException('Invalid response from OpenAI API');
    }

    public function verifyEngine(): void
    {
        $response = $this->openAi->retrieveModel($this->model);
        if (!is_string($response)) {
            throw new \RuntimeException('Invalid response from API');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from API');
        }

        if (isset($data['error']) && is_array($data['error'])) {
            $message = isset($data['error']['message']) && is_string($data['error']['message'])
                ? $data['error']['message']
                : 'API verification failed';
            throw new \RuntimeException($message);
        }

        if (!isset($data['id'])) {
            throw new \RuntimeException('Invalid response from API');
        }
    }
}
