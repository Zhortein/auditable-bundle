<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Symfony\Component\Messenger\MessageBusInterface;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;

final readonly class AsyncAuditEntryWriter implements AuditEntryWriterInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function write(PersistAuditEntryMessage $message): void
    {
        // Let Messenger routing handle the transport configuration.
        // The $transport field is ready for future enhancement (e.g., Messenger Stamp).
        $this->bus->dispatch($message);
    }
}
