<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Model;

use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;

/**
 * Describes an audit event without resolving or persisting it.
 *
 * An entity requires later identifier extraction, whereas an explicit subject
 * is already resolved. A null actor allows a resolver to run; an explicit actor
 * replaces automatic resolution. A null timestamp delegates time acquisition to
 * the future recorder.
 */
final readonly class AuditEvent
{
    public string $action;
    public string $level;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        AuditAction|string $action,
        public string $title,
        public ?string $description = null,
        public ?string $context = null,
        AuditLevel|string $level = AuditLevel::INFO,
        public ?object $entity = null,
        public ?AuditSubject $subject = null,
        public ?AuditActor $actor = null,
        public bool $isAuto = false,
        public array $data = [],
        public ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->action = $action instanceof AuditAction ? $action->value : $action;
        $this->level = $level instanceof AuditLevel ? $level->value : $level;

        if ('' === trim($this->action)) {
            throw new \InvalidArgumentException('The audit event action must not be empty.');
        }
        if ('' === trim($this->level)) {
            throw new \InvalidArgumentException('The audit event level must not be empty.');
        }
        if ('' === trim($title)) {
            throw new \InvalidArgumentException('The audit event title must not be empty.');
        }
        if (null !== $entity && null !== $subject) {
            throw new \InvalidArgumentException('An audit event cannot contain both an entity and a resolved subject.');
        }
    }
}
