<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Metadata;

use Zhortein\AuditableBundle\Attribute\Auditable;
use Zhortein\AuditableBundle\Attribute\AuditField;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;

final class AuditableMetadataProvider
{
    /** @var array<class-string, AuditableMetadata|null> */
    private array $cache = [];

    public function getFor(object $entity): ?AuditableMetadata
    {
        $class = $entity::class;

        if (\array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }

        $ref = new \ReflectionClass($class);
        $auditableAttr = $ref->getAttributes(Auditable::class)[0] ?? null;

        if (null === $auditableAttr) {
            return $this->cache[$class] = null;
        }

        /** @var Auditable $auditable */
        $auditable = $auditableAttr->newInstance();

        $label = $auditable->label ?? $ref->getShortName();
        $context = $auditable->context;

        $fieldLabels = [];
        $ignored = [];

        foreach ($ref->getProperties() as $prop) {
            if (!empty($prop->getAttributes(AuditIgnore::class))) {
                $ignored[] = $prop->getName();
                continue;
            }

            $fieldAttr = $prop->getAttributes(AuditField::class)[0] ?? null;
            if (null !== $fieldAttr) {
                /** @var AuditField $field */
                $field = $fieldAttr->newInstance();
                if (null !== $field->label && '' !== trim($field->label)) {
                    $fieldLabels[$prop->getName()] = $field->label;
                }
            }
        }

        return $this->cache[$class] = new AuditableMetadata(
            label: $label,
            context: $context,
            fieldLabels: $fieldLabels,
            ignoredFields: $ignored,
        );
    }
}
