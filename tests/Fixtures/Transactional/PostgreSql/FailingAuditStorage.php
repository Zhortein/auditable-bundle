<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql;

use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity\ApplicationAuditEntry;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

/** @implements AuditStorageInterface<ApplicationAuditEntry> */
final readonly class FailingAuditStorage implements AuditStorageInterface
{
    public function persist(object $entry): void
    {
        throw new \RuntimeException('Intentional transactional audit storage failure.');
    }
}
