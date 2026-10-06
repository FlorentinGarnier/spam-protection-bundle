# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Until 1.0.0, minor versions may contain breaking
changes.

## [Unreleased]

## [0.1.1] - 2026-10-06

### Changed

- The package is available on [Packagist](https://packagist.org/packages/florentingarnier/spam-protection-bundle): the installation instructions no longer declare Git repositories.

### Removed

- The `repositories` entry of `composer.json`: florentingarnier/spam-protection is now installed from Packagist.

## [0.1.0] - 2026-10-06

### Added

- Initial release: `SpamProtectionType` form type, JavaScript proof-of-work solver, `spam-protection:refresh-ip-lists` command, form theme, English / French / German translations and `spam_protection` Monolog channel.
- Support for Symfony 5.4, 6.4, 7.4 and 8.x, tested on each of them.

[Unreleased]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/FlorentinGarnier/spam-protection-bundle/releases/tag/v0.1.0
