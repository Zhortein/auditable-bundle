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
            actorType: $record->actor?->type,
            actorIdentifier: $record->actor?->identifier,
            impersonatorIdentifier: $record->actor?->impersonatorIdentifier,
            actorMetadata: $record->actor?->metadata ?? [],
            isAuto: $record->isAuto,
            data: $record->data,
        );
    }
}
