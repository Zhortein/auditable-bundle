<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql;

use Symfony\Component\Uid\Uuid;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity\ApplicationAuditEntry;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;

/** @implements AuditEntryFactoryInterface<ApplicationAuditEntry> */
final readonly class ApplicationAuditEntryFactory implements AuditEntryFactoryInterface
{
    public function create(AuditRecord $record): ApplicationAuditEntry
    {
        $actor = $record->actor;

        return new ApplicationAuditEntry(
            id: Uuid::v7()->toRfc4122(),
            occurredAt: $record->occurredAt,
            action: $record->action,
            level: $record->level,
            title: $record->title,
            description: $record->description,
            context: $record->context,
            subjectType: $record->subject?->type,
            subjectIdentifier: $record->subject?->identifier,
            actorType: $actor?->type,
            actorIdentifier: $actor?->identifier,
            impersonatorIdentifier: $actor?->impersonatorIdentifier,
            actorMetadata: null === $actor ? [] : $actor->metadata,
            isAuto: $record->isAuto,
            data: $record->data,
        );
    }
}
