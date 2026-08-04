<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

final readonly class DoctrineIdentifierExtractor implements IdentifierExtractorInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function extract(object $entity): AuditSubject
    {
        $metadata = $this->resolveMetadata($entity);
        $fields = $metadata->getIdentifierFieldNames();
        if ([] === $fields) {
            throw new IdentifierExtractionException(\sprintf('Doctrine metadata for "%s" declares no identifier field.', $metadata->getName()));
        }

        $values = $metadata->getIdentifierValues($entity);
        $canonical = [];
        foreach ($fields as $field) {
            if (!\array_key_exists($field, $values) || null === $values[$field]) {
                throw new IdentifierExtractionException(\sprintf('Identifier field "%s" is not available for mapped class "%s".', $field, $metadata->getName()));
            }
            if ($metadata->hasAssociation($field)) {
                throw new IdentifierExtractionException(\sprintf('Association identifier field "%s" on mapped class "%s" requires a custom extractor or an explicit AuditSubject.', $field, $metadata->getName()));
            }
            $canonical[$field] = $this->canonicalize($values[$field], $field, $metadata->getName());
        }

        if (1 === \count($fields)) {
            return new AuditSubject($metadata->getName(), $canonical[$fields[0]]['value']);
        }

        ksort($canonical, \SORT_STRING);
        try {
            $identifier = json_encode(
                ['format' => 'doctrine-composite-v1', 'fields' => $canonical],
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException $exception) {
            throw new IdentifierExtractionException(\sprintf('Composite identifier for mapped class "%s" cannot be encoded.', $metadata->getName()), previous: $exception);
        }

        return new AuditSubject($metadata->getName(), $identifier);
    }

    /** @return ClassMetadata<object> */
    private function resolveMetadata(object $entity): ClassMetadata
    {
        $class = $entity::class;
        try {
            $manager = $this->registry->getManagerForClass($class);
            if (null === $manager && $entity instanceof Proxy) {
                $parent = get_parent_class($entity);
                if (false !== $parent) {
                    $manager = $this->registry->getManagerForClass($parent);
                    $class = $parent;
                }
            }
        } catch (\Throwable $exception) {
            throw new IdentifierExtractionException(\sprintf('Unable to resolve a persistence manager for class "%s".', $class), previous: $exception);
        }

        if (null === $manager) {
            throw new IdentifierExtractionException(\sprintf('No persistence manager found for class "%s".', $class));
        }
        if (!$manager instanceof EntityManagerInterface) {
            throw new IdentifierExtractionException(\sprintf('Persistence manager for class "%s" is not a Doctrine ORM entity manager.', $class));
        }

        try {
            return $this->loadMetadata($manager, $class);
        } catch (\Throwable $exception) {
            throw new IdentifierExtractionException(\sprintf('Unable to load Doctrine ORM metadata for class "%s".', $class), previous: $exception);
        }
    }

    /**
     * @return ClassMetadata<object>
     *
     * @throws \Throwable
     */
    private function loadMetadata(EntityManagerInterface $manager, string $class): ClassMetadata
    {
        return $manager->getClassMetadata($class);
    }

    /** @return array{type: 'integer'|'string', value: string} */
    private function canonicalize(mixed $value, string $field, string $class): array
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }
        if (\is_int($value)) {
            return ['type' => 'integer', 'value' => (string) $value];
        }
        if ($value instanceof \Stringable) {
            try {
                $stringifier = \Closure::fromCallable([$value, '__toString']);
                $value = $stringifier();
            } catch (\Throwable $exception) {
                throw new IdentifierExtractionException(\sprintf('Stringable identifier field "%s" on mapped class "%s" could not be converted.', $field, $class), previous: $exception);
            }
        }
        if (\is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                throw new IdentifierExtractionException(\sprintf('Identifier field "%s" on mapped class "%s" contains invalid UTF-8.', $field, $class));
            }
            if ('' === $value || 1 === preg_match('/^\s+$/u', $value)) {
                throw new IdentifierExtractionException(\sprintf('Identifier field "%s" on mapped class "%s" must not be empty or blank.', $field, $class));
            }

            return ['type' => 'string', 'value' => $value];
        }

        throw new IdentifierExtractionException(\sprintf('Identifier field "%s" on mapped class "%s" has unsupported PHP type "%s"; use a custom extractor or an explicit AuditSubject.', $field, $class, get_debug_type($value)));
    }
}
