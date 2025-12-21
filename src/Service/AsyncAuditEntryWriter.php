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
        // On laisse le routage Messenger gérer le transport.
        // Le champ $transport est prêt si tu veux forcer plus tard (stamp).
        $this->bus->dispatch($message);
    }
}
