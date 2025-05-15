<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Orhanerday\OpenAi\OpenAi;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;

class FrenchGuidelinesChecker
{
    private const NBSP = "\u{00A0}";
    private const ELLIPSIS = "…";
    private const DOUBLE_PUNCTUATION = ["!", "?", ":", ";", "»"];

    /** @var array<string, string> */
    protected array $glossary = [];


    public function __construct(private ?OpenAi  $ai = null , private $model = null)
    {
        // Load the glossary CSV from docs/fr-glossary.csv
        $this->glossary = $this->loadGlossary();
    }

    /** @return array<string, string> */
    protected function loadGlossary(): array
    {
        $glossary = [];
        $file = __DIR__ . "/../docs/fr-glossary.csv";
        if (file_exists($file)) {
            $handle = fopen($file, "r");
            if ($handle) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
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
    public function check(string $content, bool $and_fix = false, bool $translate = false): array
    {
        $errors = [];
        $warnings = [];

        $loader = new PoLoader();
        $translations = $loader->loadString($content);

        foreach ($translations->getTranslations() as $translation) {
            $original = $translation->getOriginal();
            $translated = $translation->getTranslation() ?? "";

            if (empty($translated) && $translate) {
                echo "Translating $original...". PHP_EOL;
                $translated = $this->translate($original);
                if ($translated !== null) {
                    echo "Translation : $translated...". PHP_EOL;
                    $translation->translate($translated);
                }
            }

            if ($translation->isTranslated()) {
                $translated = $translation->getTranslation() ?? "";
                $result = $this->processString(
                    $translated,
                    $translation->getOriginal()
                );
                if (!empty($result["errors"])) {
                    $errors = array_merge($errors, $result["errors"]);
                }
                if (
                    $and_fix &&
                    isset($result["fixed_string"]) &&
                    $result["fixed_string"] !== null
                ) {
                    $translation->translate($result["fixed_string"]);
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
            "errors" => array_unique($errors),
            "warnings" => array_unique($warnings),
            "fixed_content" => $and_fix
                ? (new PoGenerator())->generateString($translations)
                : null,
        ];

        return $result;
    }

    /** @return array{errors: string[], fixed_string: string}|array{errors: string[]} */
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
                    "/\s*" . preg_quote($punct, "/") . "/",
                    self::NBSP . $punct,
                    $fixed
                );
            }
        }

        if (preg_match('/"[^"]*"/', $text)) {
            $errors[] = "Utiliser les guillemets français « » au lieu des guillemets droits :$text";
            $fixed = (string) preg_replace(
                '/"([^"]+)"/',
                "«" . self::NBSP . '$1' . self::NBSP . "»",
                $fixed
            );
        }

        if (str_contains($text, "'")) {
            $errors[] = "Utiliser l'apostrophe typographique (') au lieu de l'apostrophe droite (') :$text";
            $fixed = (string) str_replace("'", "’", $fixed);
        }

        if (str_contains($text, "...")) {
            $errors[] = "Utiliser le caractère unique pour les points de suspension (…) :$text";
            $fixed = (string) str_replace("...", self::ELLIPSIS, $fixed);
        }

        if (
            str_contains($text, "«") &&
            !str_contains($text, "«" . self::NBSP)
        ) {
            $errors[] = "Espace insécable manquant après « : $text";
            $fixed = (string) str_replace("«", "«" . self::NBSP, $fixed);
        }

        // Rule: No ellipsis after "etc."
        if (preg_match("/\setc(\.{2,3}|…)/u", $text)) {
            $errors[] = 'Pas de points de suspension après "etc." :' . $text;
            $fixed = preg_replace("/etc(\.{2,3}|…)/u", "etc.", $fixed);
        }

        return empty($errors)
            ? ["errors" => []]
            : ["errors" => $errors, "fixed_string" => $fixed];
    }

    public function glossaryCheck($original, $translated): array
    {
        $warnings = [];
        foreach ($this->glossary as $term => $preferred_terms) {
            // bail if not found
            if (
                !preg_match("/\b" . preg_quote($term, "/") . "\b/i", $original)
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
                implode(" ou ", $preferred_terms) .
                "' : $translated";
        }
        return $warnings;
    }

    public const SYSTEM_PROMPT = <<<PROMPT
    You are a professional translator. Translate the following text from English to French.
    PROMPT;
    public const SYSTEM_PROMPT_INTRODUCE_GLOSSARY = <<<PROMPT
    Please use these specific translations for the following terms:
    PROMPT;

    /**
     * Translates a string from English to French using the configured AI service
     * while respecting glossary terms
     * 
     * @param string $original The original English text to translate
     * @return string|null The translated text or null if translation failed
     */
    public function translate(string $original): ?string 
    {
        if (!$this->ai) {
            return null;
        }

        // Extract relevant glossary terms
        $relevantTerms = [];
        foreach ($this->glossary as $term => $preferred_terms) {
            if (preg_match("/\b" . preg_quote($term, "/") . "\b/i", $original)) {
                $relevantTerms[$term] = $preferred_terms;
            }
        }
        $systemPrompt = self::SYSTEM_PROMPT;

        // Build the prompt with glossary terms
        if (!empty($relevantTerms)) {
            $systemPrompt .= "\n".self::SYSTEM_PROMPT_INTRODUCE_GLOSSARY."\n";
            foreach ($relevantTerms as $term => $preferred) {
                $systemPrompt .= "- $term -> " . implode(" or ", $preferred) . "\n";
            }
        }

        $request = [
            'model' => $this->model ?? 'gpt-3.5-turbo',
            'messages' => [
                [
                    "role" => "system",
                    "content" => $systemPrompt
                ],
                [
                    "role" => "user",
                    "content" => $original
                ]
            ],
            'temperature' => 0.8,
        ];

        $response = $this->ai->chat($request);


        $response_array = json_decode($response, true);
        return isset($response_array['choices'][0]['message']['content']) 
            ? trim($response_array['choices'][0]['message']['content'])
            : null;
    }
}
