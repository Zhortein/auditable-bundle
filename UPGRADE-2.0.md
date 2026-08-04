# Upgrade from 1.0.0 to 2.0.0

This guide describes how to prepare an application for the future 2.x series. It does not announce a release and it does not replace application-specific migration review.

## Breaking platform changes

The principal breaking change is the supported platform:

- PHP 8.3 or later;
- Symfony 7.4 or 8.x;
- Doctrine ORM 3.x;
- DoctrineBundle 2.19 or 3.x.

Doctrine ORM 2 is no longer supported. Raising this platform baseline is why these changes belong to a new major series.

## What remains unchanged by default

With no new configuration, the historical behavior remains selected:

- `enabled` remains `true`;
- `legacy_mapping.enabled` remains `true`;
- `transactional.enabled` remains `false`;
- the `audit_entry` table keeps its historical integer identifier, columns and indexes;
- `#[Auditable]`, `#[AuditField]` and `#[AuditIgnore]` keep their contracts;
- the automatic Doctrine listener remains enabled;
- legacy errors remain fail-open: `Historizer` catches and logs them;
- legacy entity identifiers are still read only through `getId()`;
- no SQL change or migration is required merely to preserve this default mode.

## Checks before updating

- Confirm PHP, Symfony, Doctrine ORM 3 and DoctrineBundle 2.19 or 3 compatibility.
- Review and preserve the application's `composer.lock` as appropriate for its deployment process.
- Run the complete application test suite.
- Generate and review Doctrine migration diffs; do not execute an unexpected schema change blindly.
- Check Messenger routing, consumers and pending `PersistAuditEntryMessage` messages.
- Review custom implementations of legacy interfaces.
- Search for direct access to concrete bundle services or classes.
- Review the [compatibility policy](docs/compatibility.md) and [security and privacy responsibilities](docs/security-privacy.md).

## Simple legacy update

An application can update its Composer constraints and keep its existing configuration. No transactional configuration is required:

```yaml
zhortein_auditable:
  enabled: true
```

The historical mapping, listener and selected sync or async writer continue to operate. See [Legacy mode](docs/legacy-mode.md) for its preserved semantics and limitations.

## Progressive transactional activation

Enable the strict recorder explicitly:

```yaml
zhortein_auditable:
  transactional:
    enabled: true
```

The application must provide services or aliases for `AuditEntryFactoryInterface`, `AuditStorageInterface` and `Psr\Clock\ClockInterface`. The bundle supplies default aliases for `IdentifierExtractorInterface` and `AuditActorResolverInterface`; applications may replace those aliases through standard Symfony service configuration.

The factory creates the application-owned representation. The storage attaches it to the current persistence context without flushing. See the [transactional Doctrine guide](docs/transactional-doctrine.md) for wiring and transaction boundaries.

## Coexistence

Legacy storage and the strict recorder can coexist during a progressive migration:

```yaml
zhortein_auditable:
  legacy_mapping:
    enabled: true
  transactional:
    enabled: true
```

The historical `AuditEntry` mapping remains known to Doctrine while selected application paths adopt the strict recorder.

## Transactional-only mode

After every legacy path has been retired:

```yaml
zhortein_auditable:
  enabled: false
  legacy_mapping:
    enabled: false
  transactional:
    enabled: true
```

This opt-out does not remove the physical `audit_entry` table and the bundle supplies no migration. Review generated migrations manually and decide whether historical data must be retained, archived or migrated.

Before disabling the mapping, stop legacy producers, drain pending `PersistAuditEntryMessage` messages and ensure that no legacy worker can write `AuditEntry` records.

## Transaction boundary and fail-closed behavior

The strict recorder does not flush, commit, roll back or catch factory and storage exceptions. An application storage must not flush. Doctrine `persist()` does not mean commit.

Shared atomicity requires the business mutation and application audit entry to use the same `EntityManagerInterface` and underlying connection. The application owns `flush()`, `commit()` and `rollBack()`, and must allow audit exceptions to propagate so the transaction can be rolled back. After a flush or transaction failure, abandon the affected entity manager according to Doctrine practices. Never keep a transaction open across HTTP requests or user interaction.

## Identifiers

The default Doctrine extractor accepts integer, string, `BackedEnum` and `Stringable` identifier values. Primitive composite identifiers are encoded deterministically using the stable `doctrine-composite-v1` format.

Association identifier fields are not supported by the default extractor. Use a custom `IdentifierExtractorInterface` implementation or provide an explicit `AuditSubject`. The same alternatives apply when an application needs a logical subject identifier unrelated to Doctrine metadata.

## Actors

The default transactional Symfony Security resolver uses `UserInterface::getUserIdentifier()`. It resolves immediate `SwitchUserToken` impersonation and may return `null` when no user is available. Supply an explicit `AuditActor` for system or technical operations, or replace `AuditActorResolverInterface` when another identifier policy is required.

## Rollback plan

An application that retains the legacy mapping can return its own code paths to the historical services without an automatic schema migration. Application-owned audit entities, tables and migrations remain the application's responsibility; the bundle cannot undo them automatically.

No automatic downgrade of Doctrine ORM is promised. A rollback involving platform dependencies must be planned and tested by the application using its dependency lock and deployment process.
