<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder;

use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;

/** @implements AuditEntryFactoryInterface<CapturedAuditEntry> */
final class CapturingAuditEntryFactory implements AuditEntryFactoryInterface
{
    /** @var list<CapturedAuditEntry> */
    public array $entries = [];

    public function __construct(private readonly ?CallSequence $sequence = null)
    {
    }

    public function create(AuditRecord $record): CapturedAuditEntry
    {
        $this->sequence?->add('factory');
        $entry = new CapturedAuditEntry($record);
        $this->entries[] = $entry;

        return $entry;
    }
}
