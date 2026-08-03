# Transactional Doctrine integration

The strict recorder is deliberately independent from an application's persistence model. An application that stores audit entries with Doctrine supplies its own audit entity, `AuditEntryFactoryInterface` implementation, `AuditStorageInterface` implementation and PSR-20 clock. The bundle provides none of these persistence choices.

## Application-owned storage

A minimal Doctrine storage attaches the application-owned audit entity to the same Unit of Work as the business mutation:

```php
use Doctrine\ORM\EntityManagerInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

final readonly class DoctrineAuditStorage implements AuditStorageInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(object $entry): void
    {
        // Optional application-specific type validation belongs here.
        $this->entityManager->persist($entry);
    }
}
```

Calling `persist()` does not commit or even issue the insert. The storage alone does not guarantee durability. Atomicity requires the business mutation and audit storage to use the same entity manager and underlying connection; a factory or storage using another connection cannot provide the same guarantee.

## Application transaction boundary

The application owns the transaction boundary. `wrapInTransaction()` is the concise choice when one Doctrine Unit of Work covers the operation:

```php
$entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($operation, $auditRecorder): void {
    $operation->complete();
    $entityManager->persist($operation);

    $auditRecorder->record(new AuditEvent(
        action: 'complete',
        title: 'Operation completed',
        entity: $operation,
    ));
});
```

The recorder does not catch factory or storage errors. Doctrine controls flush and commit at the boundary, so a strict audit failure aborts the business transaction. Applications needing finer control can instead call `beginTransaction()`, perform the mutation and audit, call `flush()` and `commit()`, and call `rollBack()` immediately on failure.

After a flush or transaction error, abandon the entity manager according to Doctrine's transaction practices rather than attempting to reuse a potentially inconsistent Unit of Work. Never keep a database transaction open during user interaction or across HTTP requests.

The executable PostgreSQL fixtures in [`tests/Fixtures/Transactional/PostgreSql`](https://github.com/Zhortein/auditable-bundle/tree/main/tests/Fixtures/Transactional/PostgreSql) and [`TransactionalAtomicityIntegrationTest`](https://github.com/Zhortein/auditable-bundle/blob/main/tests/Integration/PostgreSql/TransactionalAtomicityIntegrationTest.php) demonstrate shared commit, shared rollback and fail-closed behavior with application-owned UUID v7 entities.

## Transactional-only mapping

During a transition, an application can keep the historical mapping while enabling the strict recorder:

```yaml
zhortein_auditable:
  legacy_mapping:
    enabled: true
  transactional:
    enabled: true
```

The legacy runtime may be enabled or disabled in this coexistence configuration, and Doctrine continues to know the bundle's `AuditEntry` entity.

Once the application no longer uses any legacy audit path, it can retain only its application-owned transactional mapping:

```yaml
zhortein_auditable:
  enabled: false
  legacy_mapping:
    enabled: false
  transactional:
    enabled: true
```

The opt-out is rejected unless `enabled` is also `false`. It only prevents registration of the bundle's legacy Doctrine mapping: it does not delete `audit_entry`, alter an existing schema or provide a migration. Historical data remains physically present until an application migration deliberately preserves, archives or changes it. Review any migration generated after removing the mapping before execution; the bundle does not recommend automatic deletion of audit history.

Before disabling the mapping in an application that used asynchronous legacy auditing:

1. Stop or disable production of legacy audits.
2. Drain or process every pending `PersistAuditEntryMessage`.
3. Verify that no legacy worker still writes `AuditEntry` records.
4. Only then disable the mapping.

A queued legacy message still depends on the legacy message handler, persister and `AuditEntry` mapping. The bundle does not drain queues automatically. The executable SQLite and PostgreSQL opt-out tests demonstrate that the strict recorder and application-owned audit schema continue to work without registering the legacy metadata.
