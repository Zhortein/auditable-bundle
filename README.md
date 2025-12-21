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

### Doctrine mapping

The bundle ships a Doctrine entity (`History`). Its mapping is registered automatically by a compiler pass, so you **don’t need** to declare a `doctrine.orm.mappings` entry manually.

Run your migrations after installation:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

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

### 2) (Recommended) Enable async writing

If your project already has Messenger with an `async` transport, you’re basically done.

Otherwise, define a transport (example):

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      async: '%env(MESSENGER_TRANSPORT_DSN)%'
```

Then configure the bundle to use it:

```yaml
# config/packages/zhortein_auditable.yaml
zhortein_auditable:
  enabled: true
  writer:
    async: true
    transport: 'async'
```

> If `async` is disabled, audits are written synchronously.

## Configuration

```yaml
# config/packages/zhortein_auditable.yaml
zhortein_auditable:
  enabled: true

  actor:
    # Strategy used to resolve "who did it"
    # Default: security_token (falls back to null if no authenticated user)
    resolver: 'security_token'

  writer:
    async: true
    transport: 'async'

  tracking:
    ip: true
    user_agent: true
    route: true
```

## What gets stored

Each `History` record typically contains:

- entity class + entity id
- action (create/update/delete)
- actor (user id / identifier, depending on resolver)
- datetime
- route + IP + user-agent (optional)
- changes (field name, old/new), excluding `#[AuditIgnore]` properties

## Security / PII

This bundle is meant to help you build **auditable applications**—but you are responsible for what you store.

- Use `#[AuditIgnore]` for secrets (password hashes, tokens) and sensitive data that should not be persisted in audit logs.
- Consider encrypting audit payloads or restricting access to the History table depending on your domain constraints.

## License

MIT (see [LICENSE](LICENSE)]).
