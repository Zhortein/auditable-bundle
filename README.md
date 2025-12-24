# Zhortein Auditable Bundle

A lightweight Symfony bundle to automatically **audit and historize Doctrine ORM entity changes** (create/update/delete) with optional **async persistence** via Symfony Messenger.

> Designed for Symfony 7.4+ / 8.x, PHP 8.3+.

## Features

- ✅ Opt-in auditing with PHP Attributes:
  - `#[Audited]` on a Doctrine entity class to enable auditing
  - `#[AuditLabel('…')]` on an entity class to override the displayed label
  - `#[AuditIgnore]` on an entity property to exclude it from audits (PII/secrets/noise)
- ✅ Captures create / update / delete actions
- ✅ Stores audit records in a `History` entity (Doctrine ORM)
- ✅ Supports async writing using Symfony Messenger (recommended)
- ✅ Extensible: actor resolver, label strategy, change detector, writer…

## Requirements

- PHP 8.3+
- Symfony 7.4+ (Symfony 8.x supported)
- Doctrine ORM + DoctrineBundle
- Symfony Messenger (optional but recommended for async)

## Installation

```bash
composer require zhortein/auditable-bundle
```

If you **don’t** use Symfony Flex recipes, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    Zhortein\AuditableBundle\ZhorteinAuditableBundle::class => ['all' => true],
];
```

### Doctrine mapping & migrations

The bundle ships a Doctrine entity (`AuditEntry`). Its mapping is registered automatically by a compiler pass, so you **don't need** to declare a `doctrine.orm.mappings` entry manually.

**Generate and run migrations** after installation:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

This creates the `audit_entry` table with the necessary schema for storing audit trail entries.

## Quick start

### 1) Mark entities as audited

```php
use Zhortein\AuditableBundle\Attribute\Audited;
use Zhortein\AuditableBundle\Attribute\AuditLabel;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;

#[Audited]
#[AuditLabel('Customer')]
class Customer
{
    private string $email;

    #[AuditIgnore]
    private string $passwordHash;
}
```

### 2) (Recommended) Configure async message handling

By default, the bundle is configured for **async persistence** via Symfony Messenger.

#### Step A: Define the Messenger transport

If your project doesn't already have a Messenger `async` transport, add one:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      async: '%env(MESSENGER_TRANSPORT_DSN)%'
    routing:
      'Zhortein\AuditableBundle\Message\PersistAuditEntryMessage': async
```

See `config/packages/messenger.yaml.example` in the bundle for a complete configuration example.

#### Step B: Configure the bundle

Create or update the bundle configuration:

```yaml
# config/packages/zhortein_auditable.yaml
zhortein_auditable:
  enabled: true
  async:
    enabled: true
    transport: 'async'
```

See `config/packages/zhortein_auditable.yaml.example` in the bundle for all available options.

#### Synchronous mode (optional)

If you prefer **synchronous persistence** (audit records written immediately without Messenger):

```yaml
zhortein_auditable:
  enabled: true
  async:
    enabled: false
```

> **Note**: Async mode is recommended for production to avoid blocking request handling with database writes.

## Configuration reference

The bundle's configuration options are documented with comments in `config/packages/zhortein_auditable.yaml.example`.

**Key settings:**

- **`enabled`**: Master switch to enable/disable auditing globally (default: `true`)
- **`async.enabled`**: Use Messenger for async persistence (default: `true`)
- **`async.transport`**: Messenger transport name for audit messages (default: `'async'`)
- **`listener.track_insert/update/delete`**: Control which operations are tracked (all default to `true`)
- **`fields.max_string_length`**: Maximum length for serialized field values (default: `180`)
- **`fields.global_ignored`**: List of properties to always exclude from all entities (default: `[]`)

**Actor resolution:**

By default, the bundle uses Symfony Security to resolve the current user via `SecurityActorResolver`. No additional configuration is needed.

The resolver automatically handles:
- Regular authenticated users → stores user ID or user identifier
- Null users (not authenticated) → stores `null`
- Impersonation → stores both original user and impersonator IDs

## What gets stored

Each `AuditEntry` record in the audit trail contains:

- **Entity metadata**: Fully qualified class name and entity ID
- **Action**: One of `create`, `update`, `delete`, or `log`
- **Level**: Severity level (`debug`, `info`, `warning`, `error`, `critical`)
- **Title & Description**: Human-readable summary of the change
- **Context**: Optional context tag for grouping related entities
- **Actor**: User ID or identifier of who made the change (null if unauthenticated)
- **Impersonator**: Original user ID if the change was made during impersonation
- **Timestamp**: When the change occurred (as `DateTimeImmutable`)
- **Data**: JSON-encoded field changes (old value → new value), excluding `#[AuditIgnore]` properties

**Example audit entry for an update:**
```
Title: "Update [Customer] - 2 field(s) changed"
Description: 
  - "Email: john@example.com → john.doe@example.com"
  - "Phone: +1234567890 → +1987654321"
Data: { "email": { "old": "john@example.com", "new": "john.doe@example.com" }, ... }
```

## Security / PII

This bundle is meant to help you build **auditable applications**—but you are responsible for what you store.

- Use `#[AuditIgnore]` for secrets (password hashes, tokens) and sensitive data that should not be persisted in audit logs.
- Consider encrypting audit payloads or restricting access to the History table depending on your domain constraints.

## License

MIT (see [LICENSE](LICENSE)]).
