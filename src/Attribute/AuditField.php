<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AuditField
{
    public function __construct(
        public ?string $label = null,
    ) {
    }
}
