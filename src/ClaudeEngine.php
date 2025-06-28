<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use RuntimeException;

class ClaudeEngine implements TranslationEngineInterface
{
    public function __construct(private ?string $model = null)
    {
    }

    public function translate(string $text, string $systemPrompt): string
    {
        $output = $this->callClaude($text, $systemPrompt);

        if ($output === false || $output === null || trim($output) === '') {
            throw new RuntimeException(
                'Failed to get response from Claude CLI'
            );
        }

        return trim($output);
    }

    public function verifyEngine(): void
    {
        $command = 'claude --help 2>&1';
        $output = '';
        $returnVar = 0;

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException(
                'Claude CLI is not available. Please install claude command-line tool.'
            );
        }
    }

    protected function callClaude(
        string $prompt,
        string $systemPrompt
    ): string|false|null {
        // Combine system prompt with the user prompt for Claude CLI
        // This ensures Claude follows the translation instructions
        $combinedPrompt = "Text to translate:\n" . $prompt;
        $escapedPrompt = escapeshellarg($combinedPrompt);
        $systemPrompt = escapeshellarg($systemPrompt);

        $command = "claude -p {$escapedPrompt} --system-prompt {$systemPrompt}";

        if ($this->model) {
            $command .= ' --model ' . escapeshellarg($this->model);
        }

        $command .= ' 2>&1';

        $output = shell_exec($command);

        return $output;
    }
}
