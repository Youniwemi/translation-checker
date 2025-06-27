<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

class ClaudeEngine implements TranslationEngineInterface
{
    private string $model;

    /**
     * @param string|null $model The Claude model to use (defaults to 'sonnet')
     */
    public function __construct(?string $model = null)
    {
        $this->model = $model ?? getenv('CLAUDE_MODEL') ?: 'sonnet';
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
        // Build the prompt
        if ($systemPrompt === null) {
            $language = Translator::getLanguageName(substr($targetLocale, 0, 2));
            $systemPrompt = sprintf(
                'Translate the following text from English to %s (%s). Context: %s. Text domain: %s.',
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

        $prompt = $systemPrompt . "\n\nOriginal text:\n" . $original;

        // Build the command
        $command = [
            'claude',
            '--model',
            $this->model,
            $prompt
        ];

        // Execute the command and return the result
        $result = $this->executeClaudeCommand($command);
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyEngine(): void
    {
        try {
            $this->executeClaudeCommand(['claude', '--version']);
        } catch (\Exception $e) {
            throw new \Exception('Claude CLI is not installed or not accessible: ' . $e->getMessage());
        }
    }

    /**
     * Execute the claude command
     * 
     * @param array $command The command array to execute
     * @return string The command output
     * @throws \Exception If the command fails
     */
    protected function executeClaudeCommand(array $command): string
    {
        // Escape command arguments
        $escapedCommand = array_map('escapeshellarg', $command);
        $commandString = implode(' ', $escapedCommand);
        
        // Execute the command
        $output = [];
        $returnVar = 0;
        exec($commandString . ' 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            $errorMessage = implode("\n", $output);
            if (strpos($errorMessage, 'command not found') !== false) {
                throw new \Exception('claude command not found');
            }
            throw new \Exception('Claude command failed: ' . $errorMessage);
        }
        
        return implode("\n", $output);
    }
}