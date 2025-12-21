<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\AuditableBundle\Entity\AuditEntry;

/**
 * @extends ServiceEntityRepository<AuditEntry>
 */
final class AuditEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEntry::class);
    }
}
