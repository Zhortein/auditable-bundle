<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;

final readonly class SyncAuditEntryWriter implements AuditEntryWriterInterface
{
    public function __construct(
        private AuditEntryPersister $persister,
    ) {
    }

    public function write(PersistAuditEntryMessage $message): void
    {
        $this->persister->persist($message);
    }
}
