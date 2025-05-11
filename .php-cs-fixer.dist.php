<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_extra_blank_lines' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,
        'blank_line_after_namespace' => true,
        'function_typehint_space' => true,
        'binary_operator_spaces' => true,
        'cast_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'object_operator_without_whitespace' => true,
        'single_quote' => true,
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => true,
        'whitespace_after_comma_in_array' => true,
        'clean_namespace' => true,
        'single_line_after_imports' => true,
    ])
    ->setFinder($finder);