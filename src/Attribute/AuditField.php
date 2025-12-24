<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Attribute;

/**
 * Provides a custom display label for an entity property in audit records.
 *
 * When applied to a property of an auditable entity, this attribute allows you to specify
 * a human-readable label for the field in audit trail entries. If not specified, the property name is used.
 *
 * @example
 * #[Auditable]
 * class Product
 * {
 *     #[AuditField(label: 'Product Code')]
 *     private string $sku;
 *
 *     #[AuditField(label: 'Unit Price')]
 *     private float $price;
 * }
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AuditField
{
    /**
     * @param string|null $label Human-readable label for the property in audit entries (defaults to property name if null or empty)
     */
    public function __construct(
        public ?string $label = null,
    ) {
    }
}
