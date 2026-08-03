<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql;

use Doctrine\ORM\EntityManagerInterface;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity\ApplicationAuditEntry;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

/** @implements AuditStorageInterface<ApplicationAuditEntry> */
final readonly class DoctrineAuditStorage implements AuditStorageInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function persist(object $entry): void
    {
        if (!$entry instanceof ApplicationAuditEntry) {
            throw new \InvalidArgumentException('DoctrineAuditStorage accepts only ApplicationAuditEntry instances.');
        }

        $this->entityManager->persist($entry);
    }
}
