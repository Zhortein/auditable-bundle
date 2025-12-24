<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;
use Zhortein\AuditableBundle\Service\AuditEntryPersister;

#[AsMessageHandler]
final readonly class PersistAuditEntryMessageHandler
{
    public function __construct(
        private AuditEntryPersister $persister,
    ) {
    }

    public function __invoke(PersistAuditEntryMessage $message): void
    {
        $this->persister->persist($message);
    }
}
