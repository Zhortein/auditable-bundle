<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Model;

/** A fully resolved audit record ready for a persistence factory. */
final readonly class AuditRecord
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public \DateTimeImmutable $occurredAt,
        public string $action,
        public string $level,
        public string $title,
        public ?string $description = null,
        public ?string $context = null,
        public ?AuditSubject $subject = null,
        public ?AuditActor $actor = null,
        public bool $isAuto = false,
        public array $data = [],
    ) {
        if ('' === trim($action)) {
            throw new \InvalidArgumentException('The audit record action must not be empty.');
        }
        if ('' === trim($level)) {
            throw new \InvalidArgumentException('The audit record level must not be empty.');
        }
        if ('' === trim($title)) {
            throw new \InvalidArgumentException('The audit record title must not be empty.');
        }
    }
}
