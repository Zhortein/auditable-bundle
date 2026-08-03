# Zhortein Auditable Bundle

Zhortein Auditable Bundle provides two audit paths for Symfony applications using Doctrine ORM:

- the compatibility-preserved legacy listener, which records selected entity changes automatically;
- an opt-in strict recorder that lets the application own its audit model, persistence and transaction boundary.

## Requirements

- PHP 8.3 or later
- Symfony 7.4 or 8.x
- Doctrine ORM 3.x
- DoctrineBundle 2.19 or 3.x

Symfony Messenger is a required package dependency and supports the legacy asynchronous writer. The bundle includes `symfony/polyfill-mbstring`; the native `mbstring` extension remains recommended for performance.

See the [compatibility and deprecation policy](docs/compatibility.md) and the [2.0 upgrade guide](UPGRADE-2.0.md) before upgrading an existing application.

## Installation

```bash
composer require zhortein/auditable-bundle
```

Without Symfony Flex recipes, enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    Zhortein\AuditableBundle\ZhorteinAuditableBundle::class => ['all' => true],
];
```

With no bundle configuration, the legacy runtime and its `AuditEntry` mapping remain enabled and the transactional recorder remains disabled. The bundle does not apply migrations automatically. Review any Doctrine migration generated for your application before running it.

## Choose an audit path

The [legacy mode](docs/legacy-mode.md) preserves the 1.0 behavior for existing applications. It is convenient for automatic create, update and delete histories, but is fail-open, flushes through its persister and does not guarantee atomicity with the business write.

The [transactional Doctrine integration](docs/transactional-doctrine.md) is opt-in. It is intended for operations where an audit failure must prevent the business commit. The application supplies its own audit entity, factory, storage and clock; the bundle supplies no default persistence model for this path.

Both paths can coexist during a migration. The [2.0 upgrade guide](UPGRADE-2.0.md) covers coexistence, transactional-only applications and rollback planning.

## Legacy quick start

Mark an entity with the actual legacy attributes:

```php
use Zhortein\AuditableBundle\Attribute\Auditable;
use Zhortein\AuditableBundle\Attribute\AuditField;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;

#[Auditable(label: 'Customer')]
final class Customer
{
    #[AuditField(label: 'Email address')]
    private string $email;

    #[AuditIgnore]
    private string $passwordHash;
}
```

The defaults are:

```yaml
zhortein_auditable:
  enabled: true
  legacy_mapping:
    enabled: true
  transactional:
    enabled: false
  async:
    enabled: true
    transport: async
```

When `async.enabled` is `true`, route `Zhortein\AuditableBundle\Message\PersistAuditEntryMessage` through Symfony Messenger. The historical `async.transport` key is preserved for compatibility; Messenger routing determines the effective transport. Set `async.enabled: false` to use the synchronous legacy writer.

The complete options and historical limitations are documented in [Legacy mode](docs/legacy-mode.md). A commented example is available at `config/packages/zhortein_auditable.yaml.example`.

## Transactional quick start

Enable the recorder explicitly:

```yaml
zhortein_auditable:
  transactional:
    enabled: true
```

Provide application services through standard Symfony aliases:

```yaml
services:
  App\Audit\AuditEntryFactory: ~
  App\Audit\AuditStorage: ~
  App\Audit\AuditClock: ~

  Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface:
    alias: App\Audit\AuditEntryFactory

  Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface:
    alias: App\Audit\AuditStorage

  Psr\Clock\ClockInterface:
    alias: App\Audit\AuditClock
```

The bundle provides default aliases for `IdentifierExtractorInterface` and `AuditActorResolverInterface`; the application may replace either alias. It intentionally provides no entry factory, storage or clock.

Keep the mutation and audit entry in the same application-owned Unit of Work:

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

The strict recorder performs no flush, commit or rollback and does not catch factory or storage exceptions. Fail-closed behavior therefore requires the audit storage to use the same entity manager and connection and not to flush independently. The application controls the transaction boundary.

No Doctrine audit entity, storage or migration is imposed by the bundle. The executable PostgreSQL proof and full boundary rules are in the [transactional Doctrine guide](docs/transactional-doctrine.md).

## Transactional-only applications

After all legacy producers, pending Messenger messages and legacy workers have been dealt with, an application may omit the legacy mapping:

```yaml
zhortein_auditable:
  enabled: false
  legacy_mapping:
    enabled: false
  transactional:
    enabled: true
```

`enabled` controls the legacy runtime, `legacy_mapping.enabled` controls only Doctrine registration of the legacy `AuditEntry`, and `transactional.enabled` controls the strict recorder. Their defaults are `true`, `true` and `false`. Disabling the mapping while legacy auditing remains enabled is rejected.

This option never drops an existing `audit_entry` table and supplies no migration. See the [upgrade guide](UPGRADE-2.0.md#transactional-only-mode) before opting out.

## Security and non-guarantees

Never audit passwords, tokens, private keys or other secrets. Use `#[AuditIgnore]`, `fields.global_ignored` and application-level factory/storage validation to minimize recorded data. Actor identifiers and audit payloads may be personal data.

The bundle does not provide encryption at rest, cryptographic signatures, hash chaining, append-only storage, retention, purge, anonymization or legal compliance. Strict transactional recording provides a fail-closed transaction boundary when integrated correctly; it does not provide tamper evidence. See [Security and privacy](docs/security-privacy.md).

## Documentation

- [Documentation index](docs/index.md)
- [Legacy mode](docs/legacy-mode.md)
- [Transactional Doctrine integration](docs/transactional-doctrine.md)
- [Upgrade from 1.0 to 2.0](UPGRADE-2.0.md)
- [Security and privacy](docs/security-privacy.md)
- [Compatibility and deprecation](docs/compatibility.md)
- [Changelog](CHANGELOG.md)

## License

MIT. See [LICENSE](LICENSE).
