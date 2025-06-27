<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

class ClaudeEngine implements TranslationEngineInterface
{
    private string $apiKey;
    private string $model;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct(string $apiKey, string $model = 'claude-3-haiku-20240307')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function translate(string $text, string $systemPrompt): string
    {
        $request = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $text],
            ],
        ];

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('cURL request failed');
        }

        if (!is_string($response)) {
            throw new \RuntimeException('Invalid response from Claude API');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Claude API');
        }

        if ($httpCode !== 200) {
            $message = isset($data['error']['message']) && is_string($data['error']['message'])
                ? $data['error']['message']
                : 'Claude API request failed with HTTP code ' . $httpCode;
            throw new \RuntimeException($message);
        }

        if (isset($data['content'][0]['text']) && is_string($data['content'][0]['text'])) {
            return $data['content'][0]['text'];
        }

        throw new \RuntimeException('Invalid response from Claude API');
    }

    public function verifyEngine(): void
    {
        // Claude doesn't have a specific model verification endpoint
        // We'll do a simple test request instead
        $testRequest = [
            'model' => $this->model,
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'user', 'content' => 'Say "OK"'],
            ],
        ];

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testRequest),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Failed to verify Claude API key');
        }

        if (!is_string($response)) {
            throw new \RuntimeException('Invalid response from Claude API');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Claude API');
        }

        if ($httpCode === 401) {
            throw new \RuntimeException('Invalid Claude API key');
        }

        if ($httpCode !== 200) {
            $message = isset($data['error']['message']) && is_string($data['error']['message'])
                ? $data['error']['message']
                : 'Claude API verification failed with HTTP code ' . $httpCode;
            throw new \RuntimeException($message);
        }

        if (!isset($data['content'][0]['text'])) {
            throw new \RuntimeException('Invalid response from Claude API during verification');
        }
    }
}