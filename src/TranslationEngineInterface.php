<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

interface TranslationEngineInterface
{
    /**
     * Translates the given text according to the context.
     *
     * @param string      $original     The original text to translate
     * @param string      $context      The context of the translation (e.g., 'en_US' or 'Theme Name')
     * @param string      $targetLocale The target locale for the translation (e.g., 'fr_FR')
     * @param string      $textDomain   The text domain for the translation
     * @param array       $glossary     Optional glossary of terms
     * @param bool        $interactive  Whether to ask for user confirmation
     * @param string|null $systemPrompt Optional custom system prompt
     * @return string The translated text
     * @throws \Exception If the translation fails
     */
    public function translate(
        string $original,
        string $context,
        string $targetLocale,
        string $textDomain,
        array $glossary = [],
        bool $interactive = false,
        ?string $systemPrompt = null
    ): string;

    /**
     * Verifies that the translation engine is properly configured.
     *
     * @throws \Exception If the engine is not properly configured
     */
    public function verifyEngine(): void;
}