# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Until 1.0.0, minor versions may contain breaking
changes.

## [Unreleased]

## [0.2.2] - 2026-10-07

### Fixed

- The rejection message is translated. Form themes render the message of a `FormError` as it is, so the
  visitor saw the translation key `florentin_garnier_spam_protection.invalid`. The key remains the message
  template of the error.

### Added

- `symfony/translation-contracts` dependency.

## [0.2.1] - 2026-10-07

### Fixed

- The JavaScript solver submits the form again from a new task. When the proof of work was solved before the
  browser had finished dispatching the submit event, Chrome ignored the new submission and the form was never
  sent.

### Added

- Tests of the JavaScript solver, run with Node.js.

## [0.2.0] - 2026-10-06

### Security

- Tokens are locked with the Symfony Lock component while they are consumed: a submission sent twice at the
  same instant is accepted only once. The `lock_factory` option (default `lock.factory`) selects the factory;
  null disables the lock.
- Requires florentingarnier/spam-protection 0.2, which counts IPv6 attempts per /64 network.

### Added

- `symfony/lock` dependency and `SymfonyTokenLock` service.

## [0.1.2] - 2026-10-06

### Fixed

- Release metadata only, no code change. The `v0.1.1` tag was rewritten after its publication on Packagist,
  which keeps the original commit: `0.1.2` contains the same code and matches its Git tag. Prefer `0.1.2`.

## [0.1.1] - 2026-10-06

### Changed

- The package is available on [Packagist](https://packagist.org/packages/florentingarnier/spam-protection-bundle): the installation instructions no longer declare Git repositories.

### Removed

- The `repositories` entry of `composer.json`: florentingarnier/spam-protection is now installed from Packagist.

## [0.1.0] - 2026-10-06

### Added

- Initial release: `SpamProtectionType` form type, JavaScript proof-of-work solver, `spam-protection:refresh-ip-lists` command, form theme, English / French / German translations and `spam_protection` Monolog channel.
- Support for Symfony 5.4, 6.4, 7.4 and 8.x, tested on each of them.

[Unreleased]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.2.2...HEAD
[0.2.2]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/FlorentinGarnier/spam-protection-bundle/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/FlorentinGarnier/spam-protection-bundle/releases/tag/v0.1.0
