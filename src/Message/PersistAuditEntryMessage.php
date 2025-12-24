<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Message;

final readonly class PersistAuditEntryMessage
{
    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(
        public string $occurredAt, // RFC3339
        public string $action,
        public string $level,
        public string $title,
        public ?string $description = null,

        public ?string $context = null,

        public ?string $entityClass = null,
        public ?string $entityId = null,

        public ?string $actorId = null,
        public ?string $impersonatorId = null,

        public bool $isAuto = false,

        public array $data = [],
    ) {
    }
}
