<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Auditable
{
    public function __construct(
        public ?string $label = null,
        public ?string $context = null,
    ) {
    }
}
