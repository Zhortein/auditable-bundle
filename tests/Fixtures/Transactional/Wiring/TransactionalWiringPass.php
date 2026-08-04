<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\FrozenClock;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;

final readonly class TransactionalWiringPass implements CompilerPassInterface
{
    public function __construct(
        private bool $provideFactory,
        private bool $provideStorage,
        private bool $provideClock,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->provideFactory) {
            $container->setAlias(AuditEntryFactoryInterface::class, CapturingAuditEntryFactory::class);
        }
        if ($this->provideStorage) {
            $container->setAlias(AuditStorageInterface::class, CapturingAuditStorage::class);
        }
        if ($this->provideClock) {
            $container->setAlias(ClockInterface::class, FrozenClock::class);
        } else {
            $container->removeAlias(ClockInterface::class);
        }
    }
}
