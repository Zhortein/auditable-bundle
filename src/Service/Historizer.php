<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Psr\Log\LoggerInterface;
use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;

final readonly class Historizer
{
    public function __construct(
        private AuditEntryWriterInterface $writer,
        private ActorResolverInterface $actorResolver,
        private LoggerInterface $logger,
        private bool $enabled = true,
    ) {
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function historize(
        AuditAction|string $action,
        string $title,
        ?string $description = null,
        ?object $entity = null,
        ?string $context = null,
        AuditLevel|string $level = AuditLevel::INFO,
        bool $isAuto = false,
        array $data = [],
    ): void {
        if (!$this->enabled) {
            return;
        }

        try {
            $actionValue = $action instanceof AuditAction ? $action->value : (string) $action;
            $levelValue = $level instanceof AuditLevel ? $level->value : (string) $level;

            $entityClass = null;
            $entityId = null;

            if (\is_object($entity)) {
                $entityClass = $entity::class;
                $entityId = $this->extractEntityId($entity);
            }

            $message = new PersistAuditEntryMessage(
                occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
                action: $actionValue,
                level: $levelValue,
                title: $title,
                description: $description,
                context: $context,
                entityClass: $entityClass,
                entityId: $entityId,
                actorId: $this->actorResolver->resolveActorId(),
                impersonatorId: $this->actorResolver->resolveImpersonatorId(),
                isAuto: $isAuto,
                data: $data,
            );

            $this->writer->write($message);
        } catch (\Throwable $e) {
            // Auditing must never break business logic flow
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    private function extractEntityId(object $entity): ?string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return null !== $id ? (string) $id : null;
        }

        return null;
    }
}
