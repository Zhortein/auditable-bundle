# Changelog

## [Unreleased]

### Changed

- Doctrine ORM 3 is now the minimum supported ORM version.
- DoctrineBundle 2.19 or 3.x is now required.
- The next release targets the 2.x series because the supported platform baseline has changed.

### Added

- Direct runtime dependency on symfony/polyfill-mbstring, allowing the bundle to run without the native mbstring extension.
- Add immutable transactional audit contracts and data-transfer objects without changing the legacy runtime behavior.
- Add a default Doctrine ORM identifier extractor for deterministic scalar, Stringable and primitive composite audit subjects.

## [1.0.0] - 2025-12-24

- Initial stable release.
