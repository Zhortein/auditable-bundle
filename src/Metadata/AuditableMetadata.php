<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Metadata;

final readonly class AuditableMetadata
{
    /**
     * @param array<string, string> $fieldLabels
     * @param list<string>          $ignoredFields
     */
    public function __construct(
        public string $label,
        public ?string $context,
        public array $fieldLabels,
        public array $ignoredFields,
    ) {
    }
}
