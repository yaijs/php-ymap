# Contributing to php-ymap

Thank you for considering contributing to php-ymap! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a new branch for your feature or bugfix
4. Make your changes
5. Push to your fork and submit a pull request

## Development Requirements

- PHP 8.1 or higher
- Composer
- IMAP extension enabled
- Extensions: iconv, json, mbstring

## Code Standards

- PHP 8.1+ syntax and features
- Strict typing: `declare(strict_types=1)` in all files
- Type hints for all parameters and return values
- PHPStan level 8 compliance (run `vendor/bin/phpstan analyse`)

## Testing

When adding new features or fixing bugs:
- Add tests if a test suite exists
- Manually test with real IMAP servers when possible
- Test edge cases (multipart messages, different encodings, inline attachments)

## Pull Request Guidelines

1. **One feature/fix per PR** - Keep pull requests focused
2. **Update CHANGELOG.md** - Document your changes under `[Unreleased]`
3. **Write clear commit messages** - Explain what and why, not just what
4. **Test your changes** - Ensure PHPStan passes and functionality works
5. **Update documentation** - Update README.md if adding new features

## Commit Message Format

```
Brief summary (50 chars or less)

More detailed explanation if needed. Explain what changed
and why, not just what was done.

- Bullet points for multiple changes
- Keep lines under 72 characters
```

## What to Contribute

We welcome:

- **Bug fixes** - Especially MIME parsing edge cases
- **Documentation improvements** - Examples, clarifications, fixes
- **Performance optimizations** - With benchmarks/profiling data
- **Test coverage** - Unit tests for message parsing, attachments, encodings
- **Security improvements** - TLS handling, input validation

Please open an issue first for:
- Major architectural changes
- New features that expand scope significantly
- Breaking API changes

## Code Review Process

1. Maintainer will review your PR within a few days
2. Address any feedback or requested changes
3. Once approved, maintainer will merge

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
