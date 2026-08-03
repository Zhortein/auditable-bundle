<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder;

use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;

final readonly class CapturedAuditEntry
{
    public function __construct(public AuditRecord $record)
    {
    }
}
