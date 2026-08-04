<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\Transactional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\SecurityActorResolver;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TransactionalWiringTestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CallSequence;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\StrictAuditRecorder;

final class TransactionalRecorderWiringIntegrationTest extends TestCase
{
    public function testDisabledModePreservesTheHistoricalContainerAndSchema(): void
    {
        $kernel = new TestKernel();
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            self::assertFalse($container->has(AuditRecorderInterface::class));
            self::assertFalse($container->has(StrictAuditRecorder::class));
            $testContainer = $container->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            self::assertInstanceOf(AsyncAuditEntryWriter::class, $testContainer->get(AuditEntryWriterInterface::class));
            self::assertInstanceOf(SecurityActorResolver::class, $testContainer->get(ActorResolverInterface::class));

            $entityManager = $testContainer->get('doctrine.orm.entity_manager');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertSame(
                ['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'],
                self::sortedTableNames($entityManager->getMetadataFactory()->getAllMetadata()),
            );
            (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
            $tables = $entityManager->getConnection()->createSchemaManager()->listTableNames();
            sort($tables);
            self::assertSame(['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'], $tables);
        } finally {
            self::shutdownAndRemove($kernel);
        }
    }

    public function testEnabledModeAutowiresApplicationStrategiesWithoutMakingRecorderPublic(): void
    {
        $kernel = new TransactionalWiringTestKernel(TransactionalWiringTestKernel::ENABLED);
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $consumer = $container->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);
            self::assertInstanceOf(StrictAuditRecorder::class, $consumer->recorder());
            self::assertFalse($container->has(StrictAuditRecorder::class));
            self::assertFalse($container->has(AuditRecorderInterface::class));

            $subject = new AuditSubject('order', 'order-42');
            $actor = new AuditActor('user', 'david');
            $consumer->record(new AuditEvent(
                action: 'approve',
                title: 'Order approved',
                description: 'Explicit wiring test',
                context: 'orders',
                subject: $subject,
                actor: $actor,
                data: ['order' => 42],
            ));

            $storage = $container->get(CapturingAuditStorage::class);
            self::assertInstanceOf(CapturingAuditStorage::class, $storage);
            self::assertCount(1, $storage->entries);
            $entry = $storage->entries[0];
            self::assertSame('2026-08-03T10:15:30+02:00', $entry->record->occurredAt->format(\DateTimeInterface::ATOM));
            self::assertSame($subject, $entry->record->subject);
            self::assertSame($actor, $entry->record->actor);
            self::assertSame('approve', $entry->record->action);
            self::assertSame(['order' => 42], $entry->record->data);

            $testContainer = $container->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $factory = $testContainer->get(CapturingAuditEntryFactory::class);
            self::assertInstanceOf(CapturingAuditEntryFactory::class, $factory);
            self::assertSame($factory->entries[0], $storage->entries[0]);
            $sequence = $container->get(CallSequence::class);
            self::assertInstanceOf(CallSequence::class, $sequence);
            self::assertSame(['clock', 'factory', 'storage'], $sequence->calls);

            $entityManager = $testContainer->get('doctrine.orm.entity_manager');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertSame([
                AuditEntry::class,
                'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Entity\\AuditableEntity',
                'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Entity\\NonAuditableEntity',
            ], self::sortedClassNames($entityManager->getMetadataFactory()->getAllMetadata()));
            self::assertSame(
                ['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'],
                self::sortedTableNames($entityManager->getMetadataFactory()->getAllMetadata()),
            );
        } finally {
            self::shutdownAndRemove($kernel);
        }
    }

    #[DataProvider('missingDependencyProvider')]
    public function testEnabledModeFailsWhenAnApplicationStrategyIsMissing(string $environment, string $interface): void
    {
        $kernel = new TransactionalWiringTestKernel($environment);
        self::removeDirectory($kernel->getCacheDir());

        try {
            $kernel->boot();
            self::fail('The kernel boot unexpectedly succeeded without '.$interface.'.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString($interface, self::exceptionMessages($exception));
        } finally {
            self::shutdownAndRemove($kernel);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingDependencyProvider(): iterable
    {
        yield 'factory' => [TransactionalWiringTestKernel::MISSING_FACTORY, AuditEntryFactoryInterface::class];
        yield 'storage' => [TransactionalWiringTestKernel::MISSING_STORAGE, AuditStorageInterface::class];
        yield 'clock' => [TransactionalWiringTestKernel::MISSING_CLOCK, \Psr\Clock\ClockInterface::class];
    }

    private static function exceptionMessages(\Throwable $exception): string
    {
        $messages = [];
        do {
            $messages[] = $exception->getMessage();
            $exception = $exception->getPrevious();
        } while (null !== $exception);

        return implode("\n", $messages);
    }

    /** @param list<ClassMetadata<object>> $metadata
     * @return list<string>
     */
    private static function sortedClassNames(array $metadata): array
    {
        $classes = array_map(static fn (ClassMetadata $classMetadata): string => $classMetadata->getName(), $metadata);
        sort($classes);

        return $classes;
    }

    /** @param list<ClassMetadata<object>> $metadata
     * @return list<string>
     */
    private static function sortedTableNames(array $metadata): array
    {
        $tables = array_map(static fn (ClassMetadata $classMetadata): string => $classMetadata->getTableName(), $metadata);
        sort($tables);

        return $tables;
    }

    private static function shutdownAndRemove(\Symfony\Component\HttpKernel\KernelInterface $kernel): void
    {
        $cacheDir = $kernel->getCacheDir();
        $kernel->shutdown();
        restore_exception_handler();
        self::removeDirectory($cacheDir);
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item instanceof \SplFileInfo && $item->isDir()) {
                rmdir($item->getPathname());
            } elseif ($item instanceof \SplFileInfo) {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
