# Legacy mode

Legacy mode is the behavior preserved from 1.0.0 for backward compatibility. It is enabled by default and is deliberately distinct from the opt-in strict transactional recorder.

## Attributes and automatic operations

The supported attributes are:

- `#[Auditable]` on an entity class;
- `#[AuditField]` on a property to provide a display label;
- `#[AuditIgnore]` on a property to exclude it from automatic change data.

The Doctrine listener observes create, update and delete operations. Automatic entries use the historical `isAuto=false` value. This behavior is preserved rather than silently reinterpreted.

## Historical persistence model

The bundle-owned `AuditEntry` entity maps to `audit_entry` with an auto-generated integer identifier. Its historical indexes cover `occurred_at` and the pair `entity_class, entity_id`. Principal columns include occurrence time, action, level, title, description, context, entity class and identifier, actor and impersonator identifiers, `is_auto`, and JSON data.

Actor and target identifiers are stored as strings. Legacy entity extraction calls `getId()` only and does not support composite identifiers. The legacy security resolver prefers `getId()` when available and otherwise uses `getUserIdentifier()`.

## Fail-open behavior and flush

`Historizer` catches every `Throwable`, logs the error through PSR-3 and does not rethrow it. This is fail-open behavior: an audit failure does not interrupt the business flow.

`AuditEntryPersister` calls `persist()` and then `flush()`. Consequently, legacy mode provides no guarantee that a business mutation and its audit entry share one application-controlled atomic transaction. Applications requiring fail-closed shared commit and rollback should use the [strict transactional mode](transactional-doctrine.md).

## Synchronous and asynchronous writers

With `async.enabled=false`, `SyncAuditEntryWriter` invokes the legacy persister immediately. With `async.enabled=true`, `AsyncAuditEntryWriter` dispatches `PersistAuditEntryMessage` to Messenger and persistence occurs when its handler runs.

Messenger routing determines the effective transport. The historical `async.transport` configuration key and parameter remain available for backward compatibility, but the writer does not use them to select a transport. Route `PersistAuditEntryMessage` explicitly in Messenger configuration.

Asynchronous delivery separates business and audit processing and therefore does not provide a shared database transaction. Before disabling the legacy mapping, drain pending messages and stop legacy workers.

## Configuration

- `enabled` defaults to `true` and controls the legacy runtime.
- `legacy_mapping.enabled` defaults to `true` and controls Doctrine registration of `AuditEntry`; it cannot be disabled while `enabled=true`.
- `async.enabled` defaults to `true` and selects the legacy writer.
- `async.transport` defaults to `async` but does not replace Messenger routing.
- `listener.track_insert`, `track_update` and `track_delete` default to `true`.
- `fields.max_string_length` defaults to `180`.
- `fields.global_ignored` defaults to an empty list.

Disabling the mapping never deletes an existing table and no migration is supplied.

## When to use transactional mode

Prefer transactional mode when an audit failure must prevent the business commit, when business and audit rows must share commit or rollback, when composite or non-`getId()` subjects are required, or when the application must own its audit entity, factory, storage and retention model.

See also [Upgrade 2.0](../UPGRADE-2.0.md) and [Security and privacy](security-privacy.md).
