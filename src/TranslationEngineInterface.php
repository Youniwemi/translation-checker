<?php

declare(strict_types=1);

namespace Youniwemi\TranslationChecker;

interface TranslationEngineInterface
{
    public function translate(string $text, string $systemPrompt): string;

    public function verifyEngine(): void;
}
