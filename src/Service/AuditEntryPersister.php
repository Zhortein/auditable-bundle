<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;

final readonly class AuditEntryPersister
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(PersistAuditEntryMessage $message): void
    {
        $entry = new AuditEntry();
        $entry->setOccurredAt(new \DateTimeImmutable($message->occurredAt));
        $entry->setAction($message->action);
        $entry->setLevel($message->level);
        $entry->setTitle($message->title);
        $entry->setDescription($message->description);
        $entry->setContext($message->context);
        $entry->setEntityClass($message->entityClass);
        $entry->setEntityId($message->entityId);
        $entry->setActorId($message->actorId);
        $entry->setImpersonatorId($message->impersonatorId);
        $entry->setIsAuto($message->isAuto);
        $entry->setData($message->data);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
