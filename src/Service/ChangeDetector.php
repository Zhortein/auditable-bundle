<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ChangeDetector
{
    public function __construct(
        private int $maxStringLength = 180,
    ) {
    }

    /**
     * @return array<string, array{old: string, new: string}>
     */
    public function getChanges(EntityManagerInterface $em, object $entity): array
    {
        $uow = $em->getUnitOfWork();
        $uow->computeChangeSets();

        $changes = $uow->getEntityChangeSet($entity);

        $formatted = [];
        foreach ($changes as $field => [$old, $new]) {
            $formatted[$field] = [
                'old' => $this->stringify($old),
                'new' => $this->stringify($new),
            ];
        }

        return $formatted;
    }

    private function stringify(mixed $value): string
    {
        if (null === $value) {
            return '∅';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \BackedEnum) {
            return $this->truncate(\sprintf('%s (%s)', $value->name, (string) $value->value));
        }

        if ($value instanceof Collection) {
            $count = $value->count();
            $sample = [];

            foreach ($value as $item) {
                if (method_exists($item, '__toString')) {
                    $sample[] = (string) $item;
                } elseif (method_exists($item, 'getName')) {
                    /* @phpstan-ignore-next-line method.undefined */
                    $sample[] = (string) $item->getName();
                } elseif (method_exists($item, 'getTitle')) {
                    /* @phpstan-ignore-next-line method.undefined */
                    $sample[] = (string) $item->getTitle();
                } elseif (method_exists($item, 'getId')) {
                    /* @phpstan-ignore-next-line method.undefined */
                    $sample[] = \sprintf('%s#%s', (new \ReflectionClass($item))->getShortName(), (string) $item->getId());
                } else {
                    $sample[] = (new \ReflectionClass($item))->getShortName();
                }

                if (\count($sample) >= 3) {
                    break;
                }
            }

            return $this->truncate(\sprintf(
                '[Collection %d items: %s%s]',
                $count,
                implode(', ', $sample),
                $count > 3 ? ', …' : ''
            ));
        }

        if (\is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->truncate((string) $value);
            }
            if (method_exists($value, 'getName')) {
                return $this->truncate(\sprintf('[%s#%s]', (new \ReflectionClass($value))->getShortName(), (string) $value->getName()));
            }
            if (method_exists($value, 'getTitle')) {
                return $this->truncate(\sprintf('[%s#%s]', (new \ReflectionClass($value))->getShortName(), (string) $value->getTitle()));
            }
            if (method_exists($value, 'getId')) {
                return $this->truncate(\sprintf('[%s#%s]', (new \ReflectionClass($value))->getShortName(), (string) $value->getId()));
            }

            return $this->truncate(\sprintf('[object %s]', (new \ReflectionClass($value))->getShortName()));
        }

        if (\is_array($value)) {
            try {
                return $this->truncate(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
            } catch (\Throwable) {
                return '[array serialization error]';
            }
        }

        return $this->truncate((string) $value);
    }

    private function truncate(string $value): string
    {
        if ($this->maxStringLength <= 0) {
            return $value;
        }

        if (mb_strlen($value) <= $this->maxStringLength) {
            return $value;
        }

        return mb_substr($value, 0, $this->maxStringLength - 1).'…';
    }
}
