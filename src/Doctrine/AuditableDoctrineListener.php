<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;
use Zhortein\AuditableBundle\Metadata\AuditableMetadataProvider;
use Zhortein\AuditableBundle\Service\ChangeDetector;
use Zhortein\AuditableBundle\Service\Historizer;

#[AsDoctrineListener(event: Events::onFlush, priority: 0)]
#[AsDoctrineListener(event: Events::postPersist, priority: 0)]
#[AsDoctrineListener(event: Events::preRemove, priority: 0)]
final readonly class AuditableDoctrineListener
{
    /**
     * @param list<string> $globalIgnoredFields
     */
    public function __construct(
        private Historizer $historizer,
        private AuditableMetadataProvider $metadataProvider,
        private ChangeDetector $changeDetector,
        private bool $enabled = true,
        private bool $trackInsert = true,
        private bool $trackUpdate = true,
        private bool $trackDelete = true,
        private array $globalIgnoredFields = [],
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->enabled || !$this->trackUpdate) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $meta = $this->metadataProvider->getFor($entity);
            if (null === $meta) {
                continue;
            }

            $changes = $this->changeDetector->getChanges($em, $entity);
            if ([] === $changes) {
                continue;
            }

            $ignored = array_values(array_unique(array_merge($meta->ignoredFields, $this->globalIgnoredFields)));
            $fieldLabels = $meta->fieldLabels;

            $summaryLines = [];
            $moreInfos = [];

            foreach ($changes as $field => $data) {
                if (\in_array($field, $ignored, true)) {
                    continue;
                }

                $label = $fieldLabels[$field] ?? $field;
                $line = \sprintf('%s : %s → %s', $label, $data['old'], $data['new']);

                $summaryLines[] = $line;
                $moreInfos[$label] = $line;
            }

            if ([] === $summaryLines) {
                continue;
            }

            $title = \sprintf('Update [%s] - %d field(s) changed', $meta->label, \count($summaryLines));
            $description = implode("\n", $summaryLines);

            $this->historizer->historize(
                AuditAction::UPDATE,
                $title,
                $description,
                $entity,
                $meta->context,
                AuditLevel::INFO,
                false,
                $moreInfos,
            );
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if (!$this->enabled || !$this->trackInsert) {
            return;
        }

        $entity = $args->getObject();
        $meta = $this->metadataProvider->getFor($entity);
        if (null === $meta) {
            return;
        }

        $this->historizer->historize(
            AuditAction::CREATE,
            \sprintf('Create [%s]', $meta->label),
            \sprintf('Entity created: %s', $entity::class),
            $entity,
            $meta->context,
            AuditLevel::INFO,
        );
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if (!$this->enabled || !$this->trackDelete) {
            return;
        }

        $entity = $args->getObject();
        $meta = $this->metadataProvider->getFor($entity);
        if (null === $meta) {
            return;
        }

        $this->historizer->historize(
            AuditAction::DELETE,
            \sprintf('Delete [%s]', $meta->label),
            \sprintf('Entity removed: %s', $entity::class),
            $entity,
            $meta->context,
            AuditLevel::INFO,
        );
    }
}
