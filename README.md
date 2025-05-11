# French Typography & Translation Checker

A PHP package for ensuring proper French typography and consistent terminology in PO/MO translation files. Checks are split into errors (must fix) and warnings (suggestions).

The main idea is to help developers and translators maintain high-quality French translations by enforcing typographic rules and checking for consistency in terminology. The rules of typography are based on the [Les règles typographiques utilisées pour la traduction de WordPress](https://fr.wordpress.org/team/handbook/polyglots/les-regles-typographiques-utilisees-pour-la-traduction-de-wp-en-francais/).

## Features

### Typography Rules
- Non-breaking spaces before double punctuation (!, ?, :, ;, »)
- French quotation marks (« ») with proper spacing
- Typographic apostrophes (')
- Proper ellipsis character (…)

### Translation Features
- PO file parsing and generation
- Translation consistency checking via glossary

## French Typography Reference

The implemented rules and upcoming are listed in [docs/french-rules.md](docs/french-rules.md)

## Installation

```bash
composer require youniwemi/translation-checker
```

## Usage

### Command Line

Check a file:
```bash
vendor/bin/check-french fr.po
```

Check and fix issues:
```bash
vendor/bin/check-french --fix fr.po
```

Process multiple files:
```bash
vendor/bin/check-french --fix *.po
```

Options:
- `--fix` Fix issues and save changes
- `--quiet` Only show errors and warnings (no progress info)
- `--no-warnings` Only show errors (ignore warnings)
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
