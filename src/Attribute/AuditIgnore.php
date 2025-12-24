<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Attribute;

/**
 * Excludes a property from audit trail tracking.
 *
 * When applied to a property of an auditable entity, this attribute prevents that property's changes
 * from being recorded in the audit trail. Use this for sensitive data (passwords, tokens), PII that
 * should not be persisted in logs, or noisy fields (timestamps updated on every operation).
 *
 * @example
 * #[Auditable]
 * class User
 * {
 *     private string $email;
 *
 *     #[AuditIgnore]
 *     private string $passwordHash;
 *
 *     #[AuditIgnore]
 *     private ?string $apiToken;
 *
 *     #[AuditIgnore]
 *     private \DateTimeImmutable $lastActivityAt;
 * }
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AuditIgnore
{
}
