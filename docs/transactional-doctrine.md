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

The executable PostgreSQL fixtures in [`tests/Fixtures/Transactional/PostgreSql`](../tests/Fixtures/Transactional/PostgreSql) and [`TransactionalAtomicityIntegrationTest`](../tests/Integration/PostgreSql/TransactionalAtomicityIntegrationTest.php) demonstrate shared commit, shared rollback and fail-closed behavior with application-owned UUID v7 entities.
