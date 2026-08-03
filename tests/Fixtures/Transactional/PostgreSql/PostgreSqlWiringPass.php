<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

final readonly class PostgreSqlWiringPass implements CompilerPassInterface
{
    public function __construct(private bool $failingStorage)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(AuditEntryFactoryInterface::class, ApplicationAuditEntryFactory::class);
        $container->setAlias(
            AuditStorageInterface::class,
            $this->failingStorage ? FailingAuditStorage::class : DoctrineAuditStorage::class,
        );
        $container->setAlias(ClockInterface::class, PostgreSqlFrozenClock::class);
    }
}
