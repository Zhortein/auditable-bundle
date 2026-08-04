<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder;

use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

/** @implements AuditStorageInterface<CapturedAuditEntry> */
final class CapturingAuditStorage implements AuditStorageInterface
{
    /** @var list<CapturedAuditEntry> */
    public array $entries = [];

    public function __construct(private readonly ?CallSequence $sequence = null)
    {
    }

    public function persist(object $entry): void
    {
        $this->sequence?->add('storage');
        $this->entries[] = $entry;
    }
}
