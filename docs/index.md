# Zhortein Auditable Bundle Documentation

A lightweight Symfony bundle for automatic audit trail tracking on Doctrine ORM entities.

## Overview

This bundle provides a declarative, attribute-based system to automatically track changes to your Doctrine entities. When you mark an entity with `#[Auditable]`, the bundle's Doctrine listener automatically captures:

- **Entity creations** (INSERT)
- **Entity modifications** (UPDATE) with field-level change detection
- **Entity deletions** (DELETE)

All audit entries are persisted to a dedicated `audit_entry` table, with optional async processing via Symfony Messenger.

## Core Features

### ✅ Attribute-based configuration
- `#[Auditable]` on entity classes to enable auditing
- `#[AuditField(label: '...')]` on properties for custom field labels
- `#[AuditIgnore]` on sensitive fields to exclude from audit logs

### ✅ Change detection
- Automatic diff of old → new values for UPDATE operations
- Smart stringification of complex types (Enums, Collections, Objects, etc.)
- Configurable truncation of long values

### ✅ Actor tracking
- Integration with Symfony Security to track "who did it"
- Automatic impersonation detection (original user vs impersonator)
- Fallback to null for unauthenticated actions

### ✅ Flexible persistence
- **Async mode** (recommended): Messages dispatched to Messenger, processed in background
- **Sync mode**: Audit entries written immediately within the request lifecycle
- Easy toggle between modes via configuration

### ✅ Production-ready
- Full type checking (PHPStan level 8)
- Comprehensive test coverage
- Follows Symfony conventions and best practices
- Stable API suitable for package distribution

## Quick links

- [Installation & Quick Start](../README.md#installation)
- [Configuration Reference](../README.md#configuration-reference)
- [Security & PII Handling](../README.md#security--pii)
- [GitHub Repository](https://github.com/zhortein/auditable-bundle)

## Architecture

The bundle consists of:

- **Attributes**: Configuration metadata for entities and fields
- **Metadata Provider**: Reflection-based extraction of audit configuration
- **Change Detector**: Computes diffs between old and new entity states
- **Historizer**: Main service for recording audit entries (entry point for custom logging)
- **Doctrine Listener**: Intercepts entity lifecycle events to trigger auditing
- **Writers**: Sync/Async implementations for persisting audit data
- **Message Handler**: Processes queued audit messages from Messenger

## Example usage

```php
use Zhortein\AuditableBundle\Attribute\Auditable;
use Zhortein\AuditableBundle\Attribute\AuditField;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;

#[Auditable(label: 'Customer')]
class Customer
{
    #[AuditField(label: 'Email Address')]
    private string $email;

    #[AuditField(label: 'Full Name')]
    private string $name;

    #[AuditIgnore]
    private string $passwordHash;
}

// In a controller/service:
$customer->setEmail('new@example.com');
$em->persist($customer);
$em->flush();

// Automatically generates an audit entry:
// Title: "Update [Customer] - 2 field(s) changed"
// Description: "Email Address: old@example.com → new@example.com"
```

## Further reading

- See [README.md](../README.md) for installation and configuration
- Review source code documentation in `/src` for detailed API information
- Check [tests](../tests) for integration examples
