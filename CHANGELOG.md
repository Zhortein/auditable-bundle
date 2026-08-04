# Changelog

## [Unreleased]

## [2.0.0] - 2026-08-04

### Added

- Add immutable transactional contracts and models for events, records, subjects and actors.
- Add a deterministic Doctrine identifier extractor for scalar, stringable and primitive composite identifiers using the stable `doctrine-composite-v1` format.
- Add a Symfony Security actor resolver supporting authenticated users and immediate impersonation context.
- Add a strict recorder that delegates entity creation and persistence to application-provided factory and storage implementations and uses an application-provided PSR-20 clock.
- Add opt-in Symfony container wiring for the strict recorder. The bundle provides no default transactional factory, storage or clock.
- Add PostgreSQL-backed executable tests proving shared commit, shared rollback, absence of hidden flush and fail-closed rollback with application-owned UUID v7 entities.
- Add a guarded opt-out of the legacy Doctrine mapping for transactional-only applications. The opt-out is disabled by default and never removes a table.
- Add immutable 1.0 and 2.0 public API snapshots.

### Changed

- Upgrade static analysis to PHPStan 2 at maximum level without a baseline or global ignored errors.
- Include the versioned documentation in the Composer archive and validate its relative links, no-development installation and runtime autoload.

### Compatibility

- Require Doctrine ORM 3.x and DoctrineBundle 2.19 or 3.x for the 2.x series.
- Support PHP 8.3 through 8.5 and Symfony 7.4, 8.0 and 8.1 on the executed CI boundaries.
- Add a direct runtime dependency on `symfony/polyfill-mbstring`, allowing operation without the native extension.
- Preserve the default legacy runtime, `AuditEntry` mapping, table schema, service aliases and fail-open behavior. Existing applications need no mandatory SQL migration when retaining the default mapping.

### Documentation

- Add guides for upgrading from 1.0, legacy behavior, transactional Doctrine integration, security and privacy, and the 2.x compatibility and deprecation policy.

## [1.0.0] - 2025-12-24

- Initial stable release.
