# Zhortein Auditable Bundle documentation

Zhortein Auditable Bundle supports the compatibility-preserved legacy listener and an opt-in strict transactional recorder. Start with the [README overview](../README.md) to choose the appropriate path.

## Guides

- [Overview and quick starts](../README.md)
- [Legacy mode](legacy-mode.md) — automatic Doctrine lifecycle auditing and its historical fail-open semantics
- [Transactional Doctrine integration](transactional-doctrine.md) — application-owned persistence and transaction boundaries
- [Upgrade from 1.0 to 2.0](../UPGRADE-2.0.md) — platform changes, migration paths and rollback planning
- [Security and privacy](security-privacy.md) — data minimization, access, retention and guarantees not provided
- [Compatibility and deprecation](compatibility.md) — supported matrices, API freezes and 2.x policy
- [Changelog](../CHANGELOG.md)

The transactional PostgreSQL suite proves shared commit, shared rollback and fail-closed rollback. SQLite covers most container and mapping integration tests. Neither database choice imposes an audit entity or storage on consuming applications.
