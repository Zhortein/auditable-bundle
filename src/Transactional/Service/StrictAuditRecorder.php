<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Service;

use Psr\Clock\ClockInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;

/**
 * @template TEntry of object
 */
final readonly class StrictAuditRecorder implements AuditRecorderInterface
{
    /**
     * @param AuditEntryFactoryInterface<TEntry> $entryFactory
     * @param AuditStorageInterface<TEntry>      $storage
     */
    public function __construct(
        private IdentifierExtractorInterface $identifierExtractor,
        private AuditActorResolverInterface $actorResolver,
        private ClockInterface $clock,
        private AuditEntryFactoryInterface $entryFactory,
        private AuditStorageInterface $storage,
    ) {
    }

    public function record(AuditEvent $event): void
    {
        $subject = $event->subject ?? (null !== $event->entity ? $this->identifierExtractor->extract($event->entity) : null);
        $actor = $event->actor ?? $this->actorResolver->resolveActor();
        $occurredAt = $event->occurredAt ?? $this->clock->now();

        $record = new AuditRecord(
            occurredAt: $occurredAt,
            action: $event->action,
            level: $event->level,
            title: $event->title,
            description: $event->description,
            context: $event->context,
            subject: $subject,
            actor: $actor,
            isAuto: $event->isAuto,
            data: $event->data,
        );

        $entry = $this->entryFactory->create($record);
        $this->storage->persist($entry);
    }
}
