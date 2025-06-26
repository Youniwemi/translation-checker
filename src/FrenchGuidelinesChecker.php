<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;

class FrenchGuidelinesChecker
{
    public const NBSP = "\u{00A0}";
    public const ELLIPSIS = '…';
    public const DOUBLE_PUNCTUATION = ['!', '?', ':', ';', '»'];

    public function __construct(private ?Translator $translator = null)
    {
    }

    /** @return array<string, array<string>> */
    public static function loadGlossary(string $lang = 'fr'): array
    {
        $glossary = [];
        $file = __DIR__ . "/../docs/$lang-glossary.csv";
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
     * @param bool $translate Whether to translate missing strings
     * @param string $targetLang The target language code
     * @return array{errors: string[], warnings: string[], fixed_content: string|null}
     */
    public function check(
        string $content,
        bool $and_fix = false,
        bool $translate = false,
        string $targetLang = 'fr'
    ): array {
        $errors = [];
        $warnings = [];
        $stop_translation = false;

        $loader = new PoLoader();
        $translations = $loader->loadString($content);
        $glossaryTerms = self::loadGlossary($targetLang);

        foreach ($translations->getTranslations() as $translation) {
            $original = $translation->getOriginal();
            $translated = $translation->getTranslation() ?? '';

            if (empty($translated) && $translate && !$stop_translation) {
                $suggestion = $this->translate(
                    $original,
                    $targetLang,
                    $glossaryTerms
                );
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

                // Only apply typography rules for French
                if ($targetLang === 'fr') {
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

                    // Only check glossary for French
                    $glossaryResult = $this->glossaryCheck(
                        $translation->getOriginal(),
                        $translated,
                        $glossaryTerms
                    );
                    $warnings = array_merge($glossaryResult['warnings'], $warnings);

                    // Add glossary review comments when fixing
                    if ($and_fix && !empty($glossaryResult['comments'])) {
                        foreach ($glossaryResult['comments'] as $comment) {
                            $translation->getComments()->add($comment);
                        }
                    }
                }
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

        // Rule: Non-breaking space before percentage sign
        if (str_contains($text, '%') && !str_contains($text, self::NBSP . '%')) {
            $errors[] = "Espace insécable avant le signe de pourcentage : $text";
            $fixed = (string) preg_replace('/\s*%/', self::NBSP . '%', $fixed);
        }

        return ['errors' => $errors, 'fixed_string' => $fixed];
    }

    /**
     * Check the translation against the glossary terms
     *
     * @param string $original The original English text
     * @param string $translated The translated French text
     * @param array<string, array<string>> $glossaryTerms The glossary terms
     * @return array{warnings: array<string>, comments: array<string>} An array containing warnings and comments
     */
    public function glossaryCheck(
        string $original,
        string $translated,
        array $glossaryTerms = []
    ): array {
        $warnings = [];
        $comments = [];

        foreach ($glossaryTerms as $term => $preferred_terms) {
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

            $comments[] = "glossary-review: '$term' → '" . implode(' ou ', $preferred_terms) . "'";
        }

        return ['warnings' => $warnings, 'comments' => $comments];
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
     * Detect language code from filename
     *
     * @param string $filename The filename to analyze
     * @return string|null The detected language code or null
     */
    public function detectLanguageFromFilename(string $filename): ?string
    {
        // Pattern: xxx-fr.po, xxx-fr_FR.po, xxx-de_DE.po, etc.
        if (
            preg_match(
                '/[-_](([a-z]{2})(_[A-Z]{2})?)\.po$/',
                $filename,
                $matches
            )
        ) {
            return strtolower($matches[2]); // Return 'fr', 'de', 'es', etc.
        }
        return null;
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
        if (!$this->translator) {
            return null;
        }
        return $this->translator->translate($original, $targetLang, $glossary);
    }
}
