# French Typography & Translation Checker

[![validate and test](https://github.com/Youniwemi/translation-checker/actions/workflows/php.yml/badge.svg)](https://github.com/Youniwemi/translation-checker/actions/workflows/php.yml)

A PHP package for ensuring proper French typography and consistent terminology in PO translation files. Checks are split into errors (must fix) and warnings (suggestions).

The main idea is to help developers and translators maintain high-quality French translations by enforcing typographic rules and checking for consistency in terminology. The rules of typography are based on the [Les règles typographiques utilisées pour la traduction de WordPress](https://fr.wordpress.org/team/handbook/polyglots/les-regles-typographiques-utilisees-pour-la-traduction-de-wp-en-francais/).

This package will also allow to translate missing translations using any AI provider compatibile with the OpenAI API (OpenAI, OpenRouter, Ollama, Deepseek, etc.). The transalation can be either fully automated or interactive, allowing you to finetune the suggestions, or add them as fuzzy so you can update them in your favorite PO editor. This feature is still in development and needs your feedback.



## Features

### Multi-Language Support
- **Language Auto-Detection**: Automatically detects target language from filename (e.g., `plugin-de.po` → German)
- **French**: Full typography checking + AI translation
- **German, Spanish, Italian, Portuguese, Dutch, Arabic**: AI translation only (no typography rules)

### Typography Rules (French Only)
- Non-breaking spaces before double punctuation (!, ?, :, ;, »)
- French quotation marks (« ») with proper spacing
- Typographic apostrophes (')
- Proper ellipsis character (…)

### Translation Features
- PO file parsing and generation
- Translation consistency checking via glossary (French only)
- Interactive (or not) translation mode with OpenAI API integration (compatible with OpenAI, OpenRouter, Ollama, Deepseek, etc.)
- Supports multiple target languages based on filename detection


## French Typography Reference

The implemented rules and upcoming are listed in [docs/french-rules.md](docs/french-rules.md)

## Installation

```bash
composer require youniwemi/translation-checker
```

## Usage

### Command Line

Check a French file (typography + potential translation):
```bash
vendor/bin/check-translation plugin-fr.po
```

Check and fix French typography issues:
```bash
vendor/bin/check-translation --fix plugin-fr.po
```

Translate missing translations to German:
```bash
vendor/bin/check-translation --fix --translate plugin-de.po
```

Interactive Spanish translation:
```bash
vendor/bin/check-translation --fix --translate --interactive plugin-es.po
```

Process multiple files (auto-detects language from each filename):
```bash
vendor/bin/check-translation --fix *.po
```

**Language Detection Examples:**
- `plugin-fr.po` → French (typography checking + translation)
- `plugin-de.po` → German (translation only)
- `plugin-es_ES.po` → Spanish (translation only)
- `plugin-it_IT.po` → Italian (translation only)
- `plugin.po` → French (default, typography checking + translation)

Options:
- `--fix` Fix issues and save changes
- `--quiet` Only show errors and warnings (no progress info)
- `--no-warnings` Only show errors (ignore warnings)
- `--translate` Translate the missing translations
- `--interactive` Use interactive mode for translation
- `--glossary` Path to the glossary file (defaults to 'glossary.json')
- `--help` Show help message

## Messages

### Errors (Must Fix)

- "Espace insécable manquant avant les '!'"
- "Utiliser les guillemets français « » au lieu des guillemets droits"
- "Utiliser l'apostrophe typographique (') au lieu de l'apostrophe droite (')"
- "Accent manquant sur la majuscule"
- "Utiliser le caractère unique pour les points de suspension (…)"

### Warnings (Suggestions)
- "Le terme 'x' devrait être traduit par 'y'" - Suggests using consistent terminology from glossary

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run code style fixer
composer cs

# Run static analysis
composer stan

# Run all quality checks
composer qa
```



## License

MIT License. See [LICENSE](LICENSE) for more information.

## Contributing

1. Fork the repository
2. Create your feature branch
3. Write tests for your changes
4. Ensure tests pass
5. Submit a pull request

All tests must pass and code must follow PSR-12 standards.

## Credits and Acknowledgements

This package uses [gettext/gettext](https://packagist.org/packages/gettext/gettext) for PO/MO file parsing and generation.


## Environment Variables for Translation
When using the `--translate` option, the following environment variables are required:
- `OPENAI_API_KEY`: Your OpenAI API key
- `OPENAI_API_URL`: OpenAI API URL (optional, for custom endpoints, OpenRouter, Ollama, Deepseek, etc.)
- `OPENAI_MODEL`: Model to use (defaults to 'gpt-3.5-turbo')

### Example using Ollama
```bash
OPENAI_API_URL=http://localhost:11434 OPENAI_MODEL=llama3 check-translation --translate plugin-de.po
```

### Example using ChatGPT
```bash
OPENAI_API_KEY=http://localhost:11434 OPENAI_MODEL=gpt-4.1 check-translation --translate plugin-de.po
```
