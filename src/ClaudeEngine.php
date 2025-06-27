<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

class ClaudeEngine implements TranslationEngineInterface
{
    private ?string $model;

    public function __construct(?string $model = null)
    {
        $this->model = $model;
    }

    public function translate(string $text, string $systemPrompt): string
    {
        // Build the claude command
        $command = ['claude'];
        
        // Add the text to translate
        $command[] = '-p';
        $command[] = escapeshellarg($text);
        
        // Add the system prompt
        $command[] = '--system-prompt';
        $command[] = escapeshellarg($systemPrompt);
        
        // Add model if specified
        if ($this->model !== null) {
            $command[] = '--model';
            $command[] = escapeshellarg($this->model);
        }
        
        // Execute the command
        $fullCommand = implode(' ', $command);
        $output = [];
        $returnVar = 0;
        
        exec($fullCommand . ' 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \RuntimeException(
                'Claude command failed: ' . implode("\n", $output)
            );
        }
        
        return trim(implode("\n", $output));
    }

    public function verifyEngine(): void
    {
        // Check if claude command is available
        $output = [];
        $returnVar = 0;
        
        exec('claude --version 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \RuntimeException(
                'Claude CLI is not installed or not available. ' . 
                'Please install Claude CLI: https://github.com/anthropics/claude-cli'
            );
        }
    }
}