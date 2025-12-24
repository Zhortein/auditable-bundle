<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;

interface AuditEntryWriterInterface
{
    public function write(PersistAuditEntryMessage $message): void;
}
