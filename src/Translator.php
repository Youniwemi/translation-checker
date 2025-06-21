<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Orhanerday\OpenAi\OpenAi;

class Translator
{
    /**
     * Translator constructor.
     *
     * @param OpenAi|null $ai The OpenAI client instance (optional)
     * @param string|null $model The model to use for translation (default: 'gpt-3.5-turbo')
     * @param bool $interactive Whether to prompt the user for confirmation on translations
     */
    public function __construct(
        private ?OpenAi $ai = null,
        private ?string $model = null,
        private bool $interactive = false
    ) {
    }

    /**
     * Prompt the user for confirmation on the suggested translation
     *
     * @param string $original The original English text
     * @param string $suggested The suggested French translation
     * @param array<string, array<string>> $glossary The glossary terms
     * @return array{string|null, string|null} The translated text and a comment
     */
    public function promptUser(
        string $original,
        string $suggested,
        array $glossary = []
    ): array {
        echo "\n\033[1;33mOriginal :\033[0m\n\033[1;33m==========\033[0m\n\033[1;37m$original\033[0m\n\n";
        // show glossary to the user
        if (!empty($glossary)) {
            echo "\033[1;33mGlossary :\033[0m\n\033[1;33m==========\033[0m\n\033[1;37m";
            foreach ($glossary as $term => $preferred) {
                echo "- $term -> " . implode(' or ', $preferred) . "\n";
            }
            echo "\n";
        }
        echo "\033[1;32mSuggested translation :\033[0m\n\033[1;32m=======================\033[0m\n\033[1;37m$suggested\033[0m\n\n";
        echo "\033[1;36mChoose an action:\033[0m\n\033[1;36m==================\033[0m\n";
        echo "\033[1;37m[\033[1;32mY\033[1;37m] Accept translation\n";
        echo "[\033[1;33mW\033[1;37m] Accept but needs review later\n";
        echo "[\033[1;31mN\033[1;37m] Reject translation\n";
        echo "[\033[1;36mE\033[1;37m] Edit in default editor\n";
        echo "[\033[1;35mS\033[1;37m] Stop translation and continue later (This will save the changes)\n";
        echo "\n\033[1;37mYour choice (\033[1;32mY\033[1;37m/\033[1;33mW\033[1;37m/\033[1;31mN\033[1;37m/\033[1;36mE\033[1;37m/\033[1;35mS\033[1;37m) \033[1;37m[\033[1;32mY\033[1;37m]: \033[0m";

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open stdin');
        }
        $line = fgets($handle);
        if ($line === false) {
            throw new \RuntimeException('Failed to read from stdin');
        }
        $response = strtolower(trim($line));
        fclose($handle);

        if ($response === 'y' || $response === '') {
            return [$suggested, null];
        } elseif ($response === 'w') {
            // Add a comment to mark this translation for review
            return [$suggested, 'fuzzy'];
        } elseif ($response === 's') {
            return [null, 'stop'];
        } elseif ($response === 'e') {
            // Create a temporary file with the suggested translation
            $tmpfile = tempnam(sys_get_temp_dir(), 'translation_');
            file_put_contents($tmpfile, $suggested);

            // Get the default editor from environment, fallback to 'nano' if not set
            $editor = getenv('EDITOR') ?: 'nano';

            // Open the default editor in interactive mode
            passthru("$editor $tmpfile");

            // Read the edited content
            $translation = file_get_contents($tmpfile);

            // Clean up
            unlink($tmpfile);

            return [$translation ? trim($translation) : null, null];
        }

        return [null, null];
    }

    /** @return array<string, array<string>> */
    protected function loadGlossary(): array
    {
        $glossary = [];
        $file = __DIR__ . '/../docs/fr-glossary.csv';
        if (file_exists($file)) {
            $handle = fopen($file, 'r');
            if ($handle) {
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($data) >= 2) {
                        if (!isset($glossary[$data[0]])) {
                            $glossary[$data[0]] = [];
                        }
                        $glossary[$data[0]][] = $data[1];
                    }
                }
                fclose($handle);
            }
        }
        return array_slice($glossary, 1);
    }

    public const SYSTEM_PROMPT = <<<PROMPT
        Translate the following English text to {{TARGET_LANGUAGE}}, maintaining the original tone and formatting.
        Focus on accuracy and cultural context. Don't add or remove any information.
        It is very important to not write explanations. Do not echo my prompt. Do not remind me what I asked you for. Do not apologize. Do not self-reference. Do not use generic filler phrases. Get to the point precisely and accurately. Don't add or remove any information. Do not explain what and why, just give me your best possible result.
        PROMPT;
    public const SYSTEM_PROMPT_INTRODUCE_GLOSSARY = <<<PROMPT
        Use these exact translations for the specified terms :
        PROMPT;

    /**
     * Get language name from language code
     *
     * @param string $langCode The language code
     * @return string The language name
     */
    public static function getLanguageName(string $langCode): string
    {
        $languages = [
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ar' => 'Arabic',
        ];

        return $languages[$langCode] ?? 'Unknown';
    }

    /**
     * Translates a string from English to the target language using the configured AI service
     * while respecting glossary terms
     *
     * @param string $original The original English text to translate
     * @param string $targetLang The target language code (default: 'fr')
     * @param array<string, array<string>> $glossary The glossary terms
     * @return array{string|null, string|null}|null An array containing the translated text and a comment
     *               indicating if the translation was fuzzy or not
     *               (null if no translation was made)
     *               (null if no comment was made)
     */
    public function translate(
        string $original,
        string $targetLang = 'fr',
        array $glossary = []
    ): array|null {
        if (!$this->ai) {
            return null;
        }

        // Extract relevant glossary terms (only for French)
        $relevantTerms = [];
        if ($targetLang === 'fr') {
            foreach ($glossary as $term => $preferred_terms) {
                if (
                    preg_match(
                        "/\b" . preg_quote($term, '/') . "\b/i",
                        $original
                    )
                ) {
                    $relevantTerms[$term] = $preferred_terms;
                }
            }
        }

        // Get language name and build system prompt
        $langName = self::getLanguageName($targetLang);
        $systemPrompt = str_replace(
            '{{TARGET_LANGUAGE}}',
            $langName,
            self::SYSTEM_PROMPT
        );

        // Build the prompt with glossary terms (only for French)
        if (!empty($relevantTerms)) {
            $systemPrompt .=
                "\n" . self::SYSTEM_PROMPT_INTRODUCE_GLOSSARY . "\n";
            foreach ($relevantTerms as $term => $preferred) {
                $systemPrompt .=
                    "- $term -> " . implode(' or ', $preferred) . "\n";
            }
        }

        $request = [
            'model' => $this->model ?? 'gpt-3.5-turbo',
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

        $response = $this->ai->chat($request);
        if ($response === false || !is_string($response)) {
            return [null, null];
        }
        $response_array = json_decode($response, true);
        if (
            !is_array($response_array) ||
            !isset($response_array['choices']) ||
            !is_array($response_array['choices']) ||
            !isset($response_array['choices'][0]['message']['content'])
        ) {
            return [null, null];
        }
        $suggested = trim($response_array['choices'][0]['message']['content']);
        $flag = null;
        if ($suggested && $this->interactive) {
            [$suggested, $flag] = $this->promptUser(
                $original,
                $suggested,
                $relevantTerms
            );
        }

        return [$suggested, $flag];
    }

    /**
     * Verify API credentials by attempting to retrieve the configured model
     *
     * @return array{success: bool, error: string|null} Result with success status and error message if any
     */
    public function verifyApiCredentials(): array
    {
        if (!$this->ai || !$this->model) {
            return [
                'success' => false,
                'error' => 'API client or model not configured',
            ];
        }

        try {
            $response = $this->ai->retrieveModel($this->model);
            if ($response === false || !is_string($response)) {
                return ['success' => false, 'error' => 'No response from API'];
            }

            $result = json_decode($response, true);
            if (!is_array($result)) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from API',
                ];
            }

            // Check if there's an error in the response
            if (isset($result['error'])) {
                $errorMessage =
                    $result['error']['message'] ?? 'Unknown API error';
                return ['success' => false, 'error' => $errorMessage];
            }

            // Check if the model was successfully retrieved
            if (isset($result['id']) && $result['id'] === $this->model) {
                return ['success' => true, 'error' => null];
            }

            return [
                'success' => false,
                'error' => 'Model not found in response',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }
}
