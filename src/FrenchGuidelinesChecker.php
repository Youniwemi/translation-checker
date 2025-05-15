<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Orhanerday\OpenAi\OpenAi;

class FrenchGuidelinesChecker
{
    private const NBSP = "\u{00A0}";
    private const ELLIPSIS = '…';
    private const DOUBLE_PUNCTUATION = ['!', '?', ':', ';', '»'];

    /** @var array<string, array<string>> */
    protected array $glossary = [];

    private bool $interactive = false;

    public function __construct(
        private ?OpenAi $ai = null,
        private ?string $model = null
    ) {
        // Load the glossary CSV from docs/fr-glossary.csv
        $this->glossary = $this->loadGlossary();
    }

    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
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

    /**
     * @param string $content The content to check
     * @param bool $and_fix Whether to fix the content
     * @return array{errors: string[], warnings: string[], fixed_content: string|null}
     */
    public function check(
        string $content,
        bool $and_fix = false,
        bool $translate = false
    ): array {
        $errors = [];
        $warnings = [];
        $stop_translation = false;

        $loader = new PoLoader();
        $translations = $loader->loadString($content);

        foreach ($translations->getTranslations() as $translation) {
            $original = $translation->getOriginal();
            $translated = $translation->getTranslation() ?? '';

            if (empty($translated) && $translate && !$stop_translation) {
                $suggestion = $this->translate($original);
                if ($suggestion) {
                    [$translated, $flag] = $suggestion;
                    // We just stop, we won't handle this translation
                    if ($flag === 'stop') {
                        $stop_translation = true;
                    } elseif ($translated !== null) {
                        $translation->translate($translated);
                        if ($flag !== null) {
                            $translation->getFlags()->add($flag);
                        }
                    }
                }
            }

            if ($translation->isTranslated()) {
                $translated = $translation->getTranslation() ?? '';
                $result = $this->processString(
                    $translated,
                    $translation->getOriginal()
                );
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
                if (
                    $and_fix &&
                    $result['errors'] &&
                    $result['fixed_string'] !== null
                ) {
                    $translation->translate($result['fixed_string']);
                }

                $warnings = array_merge(
                    $this->glossaryCheck(
                        $translation->getOriginal(),
                        $translated
                    ),
                    $warnings
                );
            }
        }

        $result = [
            'errors' => array_unique($errors),
            'warnings' => array_unique($warnings),
            'fixed_content' => $and_fix
                ? (new PoGenerator())->generateString($translations)
                : null,
        ];

        return $result;
    }

    /**
     * Process a string to check for French typographical errors
     *
     * @param string $text The text to process
     * @param string $original The original English text
     * @return array{'errors':array<int,string>, 'fixed_string': string} An array containing the errors and the
     *                                 fixed string
     */
    private function processString(string $text, string $original): array
    {
        $errors = [];
        $fixed = $text;

        // Critical errors (must fix)
        foreach (self::DOUBLE_PUNCTUATION as $punct) {
            if (
                str_contains($text, $punct) &&
                !str_contains($text, self::NBSP . $punct)
            ) {
                $errors[] = "Espace insécable manquant avant '$punct' :$text";
                $fixed = (string) preg_replace(
                    "/\s*" . preg_quote($punct, '/') . '/',
                    self::NBSP . $punct,
                    $fixed
                );
            }
        }

        if (preg_match('/"[^"]*"/', $text)) {
            $errors[] = "Utiliser les guillemets français « » au lieu des guillemets droits :$text";
            $fixed = (string) preg_replace(
                '/"([^"]+)"/',
                '«' . self::NBSP . '$1' . self::NBSP . '»',
                $fixed
            );
        }

        if (str_contains($text, "'")) {
            $errors[] = "Utiliser l'apostrophe typographique (') au lieu de l'apostrophe droite (') :$text";
            $fixed = (string) str_replace("'", '’', $fixed);
        }

        if (str_contains($text, '...')) {
            $errors[] = "Utiliser le caractère unique pour les points de suspension (…) :$text";
            $fixed = (string) str_replace('...', self::ELLIPSIS, $fixed);
        }

        if (
            str_contains($text, '«') &&
            !str_contains($text, '«' . self::NBSP)
        ) {
            $errors[] = "Espace insécable manquant après « : $text";
            $fixed = (string) str_replace('«', '«' . self::NBSP, $fixed);
        }

        // Rule: No ellipsis after "etc."
        if (preg_match("/\setc(\.{2,3}|…)/u", $text)) {
            $errors[] = 'Pas de points de suspension après "etc." :' . $text;
            $fixed = (string) preg_replace("/etc(\.{2,3}|…)/u", 'etc.', $fixed);
        }

        return ['errors' => $errors, 'fixed_string' => $fixed];
    }

    /**
     * Check the translation against the glossary terms
     *
     * @param string $original The original English text
     * @param string $translated The translated French text
     * @return array<string> An array of warnings if any glossary terms are not
     *                      translated correctly
     */
    public function glossaryCheck(string $original, string $translated): array
    {
        $warnings = [];
        foreach ($this->glossary as $term => $preferred_terms) {
            // bail if not found
            if (
                !preg_match("/\b" . preg_quote($term, '/') . "\b/i", $original)
            ) {
                continue;
            }
            foreach ($preferred_terms as $preferred) {
                if (
                    str_contains(
                        strtolower($translated),
                        strtolower($preferred)
                    )
                ) {
                    continue 2;
                }
            }
            $warnings[] =
                "Le terme '$term' devrait être traduit par '" .
                implode(' ou ', $preferred_terms) .
                "' : $translated";
        }
        return $warnings;
    }

    public const SYSTEM_PROMPT = <<<PROMPT
        Translate the following English text to French, maintaining the original tone and formatting.
        Focus on accuracy and cultural context. Don't add or remove any information.
        It is very important to not write explanations. Do not echo my prompt. Do not remind me what I asked you for. Do not apologize. Do not self-reference. Do not use generic filler phrases. Get to the point precisely and accurately. Don't add or remove any information. Do not explain what and why, just give me your best possible result.
        PROMPT;
    public const SYSTEM_PROMPT_INTRODUCE_GLOSSARY = <<<PROMPT
        Use these exact translations for the specified terms :
        PROMPT;

    /**
     * Translates a string from English to French using the configured AI service
     * while respecting glossary terms
     *
     * @param string $original The original English text to translate
     * @return array{string|null, string|null}|null An array containing the translated text and a comment
     *               indicating if the translation was fuzzy or not
     *               (null if no translation was made)
     *               (null if no comment was made)
     */
    public function translate(string $original): array|null
    {
        if (!$this->ai) {
            return null;
        }

        // Extract relevant glossary terms
        $relevantTerms = [];
        foreach ($this->glossary as $term => $preferred_terms) {
            if (
                preg_match("/\b" . preg_quote($term, '/') . "\b/i", $original)
            ) {
                $relevantTerms[$term] = $preferred_terms;
            }
        }
        $systemPrompt = self::SYSTEM_PROMPT;

        // Build the prompt with glossary terms
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
}
