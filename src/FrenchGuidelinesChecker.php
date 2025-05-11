<?php
declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

use Gettext\Loader\PoLoader;
use Gettext\Generator\PoGenerator;

class FrenchGuidelinesChecker
{
    private const NBSP = "\u{00A0}";
    private const ELLIPSIS = "…";
    private const DOUBLE_PUNCTUATION = ["!", "?", ":", ";", "»"];

    protected $glossary = [];

    protected function loadGlossary(): array
    {
        $glossary = [];
        $file = __DIR__ . "/../docs/fr-glossary.csv";
        if (file_exists($file)) {
            $handle = fopen($file, "r");
            if ($handle) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if (count($data) >= 2) {
                        $glossary[$data[0]] = $data[1];
                    }
                }
                fclose($handle);
            }
        }
        return array_slice($glossary, 1);
    }

    public function check(string $content, bool $and_fix = false): array
    {
        $errors = [];
        $warnings = [];

        $loader = new PoLoader();
        $translations = $loader->loadString($content);

        // Load the glossary CSV fron docs/fr-glossary.csv
        $this->glossary = $this->loadGlossary();

        foreach ($translations as $translation) {
            if ($translation->isTranslated()) {
                $translated = $translation->getTranslation();
                $result = $this->processString(
                    $translated,
                    $translation->getOriginal()
                );
                if (!empty($result["errors"])) {
                    $errors = array_merge($errors, $result["errors"]);
                }
                if ($and_fix && isset($result["fixed_string"])) {
                    $translation->translate($result["fixed_string"]);
                }

                $original = $translation->getOriginal();
                foreach ($this->glossary as $term => $preferred) {
                    if (
                        str_contains(strtolower($original), $term) &&
                        !str_contains(strtolower($translated), $preferred)
                    ) {
                        $warnings[] = "Le terme '$term' devrait être traduit par '$preferred' : $translated";
                    }
                }
            }
        }

        return $and_fix
            ? [
                "errors" => array_unique($errors),
                "warnings" => array_unique($warnings),
                "fixed_content" => (new PoGenerator())->generateString(
                    $translations
                ),
            ]
            : [
                "errors" => array_unique($errors),
                "warnings" => array_unique($warnings),
            ];
    }

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
                $fixed = preg_replace(
                    "/\s*" . preg_quote($punct, "/") . "/",
                    self::NBSP . $punct,
                    $fixed
                );
            }
        }

        if (preg_match('/"[^"]*"/', $text)) {
            $errors[] = "Utiliser les guillemets français « » au lieu des guillemets droits :$text";
            $fixed = preg_replace(
                '/"([^"]+)"/',
                "«" . self::NBSP . '$1' . self::NBSP . "»",
                $fixed
            );
        }

        if (str_contains($text, "'")) {
            $errors[] = "Utiliser l'apostrophe typographique (') au lieu de l'apostrophe droite (') :$text";
            $fixed = str_replace("'", "’", $fixed);
        }

        if (str_contains($text, "...")) {
            $errors[] = "Utiliser le caractère unique pour les points de suspension (…) :$text";
            $fixed = str_replace("...", self::ELLIPSIS, $fixed);
        }

        if (
            str_contains($text, "«") &&
            !str_contains($text, "«" . self::NBSP)
        ) {
            $errors[] = "Espace insécable manquant après « : $text";
            $fixed = str_replace("«", "«" . self::NBSP, $fixed);
        }

        if (
            str_contains($text, "»") &&
            !str_contains($text, self::NBSP . "»")
        ) {
            $errors[] = "Espace insécable manquant avant » :$text";
            $fixed = str_replace("»", self::NBSP . "»", $fixed);
        }

        return empty($errors)
            ? ["errors" => []]
            : ["errors" => $errors, "fixed_string" => $fixed];
    }
}
