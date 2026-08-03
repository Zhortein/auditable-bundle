<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\PostgreSql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TransactionalPostgreSqlTestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\DoctrineAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity\ApplicationAuditEntry;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity\BusinessOperation;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\PostgreSqlFrozenClock;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;

final class TransactionalAtomicityIntegrationTest extends TestCase
{
    private const BUSINESS_TABLE = 'test_tx_business_operation';
    private const AUDIT_TABLE = 'test_tx_application_audit_entry';

    public function testBusinessMutationAndAuditCommitTogetherWithoutHiddenFlush(): void
    {
        [$kernel, $entityManager, $observer] = $this->bootKernel(TransactionalPostgreSqlTestKernel::NORMAL);
        $connection = $entityManager->getConnection();

        try {
            $this->assertSchema($connection);
            $this->assertStorageHasOnlyOnePersistCall();

            $consumer = $kernel->getContainer()->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);

            $connection->beginTransaction();
            $operation = new BusinessOperation('pending');
            self::assertInstanceOf(UuidV7::class, Uuid::fromString($operation->getId()));
            $entityManager->persist($operation);
            $operation->changeStatus('completed');
            self::assertSame('completed', $operation->getStatus());
            $consumer->record($this->auditEvent($operation));

            $this->assertCounts($connection, 0, 0);
            $this->assertCounts($observer, 0, 0);

            $entityManager->flush();

            $this->assertCounts($connection, 1, 1);
            $this->assertCounts($observer, 0, 0);

            $connection->commit();

            $this->assertCounts($observer, 1, 1);
            self::assertSame('completed', $observer->fetchOne('SELECT status FROM '.self::BUSINESS_TABLE));

            $entityManager->clear();
            $entry = $entityManager->getRepository(ApplicationAuditEntry::class)->findOneBy([]);
            self::assertInstanceOf(ApplicationAuditEntry::class, $entry);
            self::assertInstanceOf(UuidV7::class, Uuid::fromString($entry->getId()));
            self::assertSame(PostgreSqlFrozenClock::INSTANT, $entry->getOccurredAt()->format('Y-m-d\\TH:i:s.uP'));
            self::assertSame('complete', $entry->getAction());
            self::assertSame('notice', $entry->getLevel());
            self::assertSame('Business operation completed', $entry->getTitle());
            self::assertSame('Atomic PostgreSQL operation', $entry->getDescription());
            self::assertSame('transactional-test', $entry->getContext());
            self::assertSame(BusinessOperation::class, $entry->getSubjectType());
            self::assertSame($operation->getId(), $entry->getSubjectIdentifier());
            self::assertSame('test-user', $entry->getActorType());
            self::assertSame('david', $entry->getActorIdentifier());
            self::assertSame('administrator', $entry->getImpersonatorIdentifier());
            self::assertSame(['tenant' => 'test'], $entry->getActorMetadata());
            self::assertTrue($entry->isAuto());
            self::assertSame(['status' => ['from' => 'pending', 'to' => 'completed']], $entry->getData());
        } finally {
            $this->closeResources($kernel, $entityManager, $observer);
        }
    }

    public function testBusinessMutationAndAuditRollBackTogether(): void
    {
        [$kernel, $entityManager, $observer] = $this->bootKernel(TransactionalPostgreSqlTestKernel::NORMAL);
        $connection = $entityManager->getConnection();

        try {
            $consumer = $kernel->getContainer()->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);

            $connection->beginTransaction();
            $operation = new BusinessOperation('pending');
            $entityManager->persist($operation);
            $consumer->record($this->auditEvent($operation));
            $entityManager->flush();

            $this->assertCounts($connection, 1, 1);
            $this->assertCounts($observer, 0, 0);

            $connection->rollBack();
            $entityManager->close();

            $this->assertCounts($observer, 0, 0);
        } finally {
            $this->closeResources($kernel, $entityManager, $observer);
        }
    }

    public function testStrictAuditFailureKeepsAnAlreadyFlushedBusinessMutationRollbackable(): void
    {
        [$kernel, $entityManager, $observer] = $this->bootKernel(TransactionalPostgreSqlTestKernel::FAILING_STORAGE);
        $connection = $entityManager->getConnection();

        try {
            $consumer = $kernel->getContainer()->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);

            $connection->beginTransaction();
            $operation = new BusinessOperation('pending');
            $entityManager->persist($operation);
            $entityManager->flush();

            $this->assertCounts($connection, 1, 0);
            $this->assertCounts($observer, 0, 0);

            try {
                $consumer->record($this->auditEvent($operation));
                self::fail('The strict recorder unexpectedly absorbed the storage failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Intentional transactional audit storage failure.', $exception->getMessage());
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $entityManager->close();
            }

            $this->assertCounts($observer, 0, 0);
        } finally {
            $this->closeResources($kernel, $entityManager, $observer);
        }
    }

    public function testTransactionalApplicationCanOwnTheOnlyAuditMapping(): void
    {
        [$kernel, $entityManager, $observer] = $this->bootKernel(TransactionalPostgreSqlTestKernel::WITHOUT_LEGACY_MAPPING);
        $connection = $entityManager->getConnection();

        try {
            $metadataNames = array_map(
                static fn ($metadata): string => $metadata->getName(),
                $entityManager->getMetadataFactory()->getAllMetadata(),
            );
            sort($metadataNames);
            self::assertSame([ApplicationAuditEntry::class, BusinessOperation::class], $metadataNames);
            self::assertFalse($connection->createSchemaManager()->tablesExist(['audit_entry']));
            self::assertSame([self::AUDIT_TABLE, self::BUSINESS_TABLE], $this->sortedTableNames($connection));

            $consumer = $kernel->getContainer()->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);

            $connection->beginTransaction();
            $operation = new BusinessOperation('pending');
            $entityManager->persist($operation);
            $operation->changeStatus('completed');
            $consumer->record($this->auditEvent($operation));
            $entityManager->flush();
            $connection->commit();

            $this->assertCounts($observer, 1, 1);
            self::assertFalse($observer->createSchemaManager()->tablesExist(['audit_entry']));
            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $registry = $testContainer->get('doctrine');
            self::assertInstanceOf(ManagerRegistry::class, $registry);
            self::assertNull($registry->getManagerForClass(AuditEntry::class));
        } finally {
            $this->closeResources($kernel, $entityManager, $observer);
        }
    }

    /** @return array{TransactionalPostgreSqlTestKernel, EntityManagerInterface, Connection} */
    private function bootKernel(string $environment): array
    {
        $kernel = new TransactionalPostgreSqlTestKernel($environment);
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();

        $testContainer = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $entityManager = $testContainer->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);

        $observer = DriverManager::getConnection($entityManager->getConnection()->getParams());

        return [$kernel, $entityManager, $observer];
    }

    private function assertSchema(Connection $connection): void
    {
        $tables = $connection->createSchemaManager()->listTableNames();
        sort($tables);
        self::assertSame(['audit_entry', self::AUDIT_TABLE, self::BUSINESS_TABLE], $tables);

        foreach ([self::BUSINESS_TABLE, self::AUDIT_TABLE] as $table) {
            self::assertSame('uuid', $connection->fetchOne(
                'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, 'id'],
            ));
        }
    }

    private function assertStorageHasOnlyOnePersistCall(): void
    {
        $filename = (new \ReflectionClass(DoctrineAuditStorage::class))->getFileName();
        self::assertIsString($filename);
        $source = file_get_contents($filename);
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '$this->entityManager->persist($entry);'));
        foreach (['->flush(', 'beginTransaction(', 'commit(', 'rollBack(', 'wrapInTransaction(', 'transactional(', 'getConnection(', 'createQuery(', 'getRepository('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private function auditEvent(BusinessOperation $operation): AuditEvent
    {
        return new AuditEvent(
            action: 'complete',
            title: 'Business operation completed',
            description: 'Atomic PostgreSQL operation',
            context: 'transactional-test',
            level: 'notice',
            entity: $operation,
            actor: new AuditActor(
                type: 'test-user',
                identifier: 'david',
                impersonatorIdentifier: 'administrator',
                metadata: ['tenant' => 'test'],
            ),
            isAuto: true,
            data: ['status' => ['from' => 'pending', 'to' => 'completed']],
        );
    }

    private function assertCounts(Connection $connection, int $business, int $audit): void
    {
        /** @var int|numeric-string $businessCount */
        $businessCount = $connection->fetchOne('SELECT COUNT(*) FROM '.self::BUSINESS_TABLE);
        /** @var int|numeric-string $auditCount */
        $auditCount = $connection->fetchOne('SELECT COUNT(*) FROM '.self::AUDIT_TABLE);
        self::assertSame($business, (int) $businessCount);
        self::assertSame($audit, (int) $auditCount);
    }

    /** @return list<string> */
    private function sortedTableNames(Connection $connection): array
    {
        $tables = $connection->createSchemaManager()->listTableNames();
        sort($tables);

        return $tables;
    }

    private function closeResources(
        TransactionalPostgreSqlTestKernel $kernel,
        EntityManagerInterface $entityManager,
        Connection $observer,
    ): void {
        $connection = $entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        if ($entityManager->isOpen()) {
            $entityManager->close();
        }
        $observer->close();
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
