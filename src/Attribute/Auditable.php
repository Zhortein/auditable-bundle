<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Attribute;

/**
 * Marks a Doctrine entity as auditable.
 *
 * When applied to a class, this attribute enables automatic audit trail tracking for create/update/delete operations.
 * The bundle's Doctrine listener will intercept entity lifecycle events and persist audit entries to the History table.
 *
 * @example
 * #[Auditable(label: 'Customer', context: 'sales')]
 * class Customer
 * {
 *     #[AuditField(label: 'Customer Name')]
 *     private string $name;
 *
 *     #[AuditIgnore]
 *     private string $passwordHash;
 * }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Auditable
{
    /**
     * @param string|null $label   Human-readable label for the entity (defaults to short class name if null)
     * @param string|null $context Optional context/category to group related audited entities
     */
    public function __construct(
        public ?string $label = null,
        public ?string $context = null,
    ) {
    }
}
