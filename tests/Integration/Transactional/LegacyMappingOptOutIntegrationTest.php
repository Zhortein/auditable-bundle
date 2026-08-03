<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\Transactional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TransactionalNoLegacyMappingTestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Entity\AuditableEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;

final class LegacyMappingOptOutIntegrationTest extends TestCase
{
    public function testLegacyMappingRemainsEnabledByDefault(): void
    {
        $kernel = new TestKernel();
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $registry = $container->get('doctrine');
            self::assertInstanceOf(ManagerRegistry::class, $registry);
            $manager = $registry->getManagerForClass(AuditEntry::class);
            self::assertInstanceOf(EntityManagerInterface::class, $manager);
            self::assertSame('audit_entry', $manager->getClassMetadata(AuditEntry::class)->getTableName());

            (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
            self::assertTrue($manager->getConnection()->createSchemaManager()->tablesExist(['audit_entry']));
        } finally {
            $cacheDir = $kernel->getCacheDir();
            $kernel->shutdown();
            restore_exception_handler();
            self::removeDirectory($cacheDir);
        }
    }

    public function testTransactionalRecorderWorksWithoutLegacyMapping(): void
    {
        $kernel = new TransactionalNoLegacyMappingTestKernel();
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();

        try {
            self::assertTrue(class_exists(AuditEntry::class));
            $testContainer = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);
            $registry = $testContainer->get('doctrine');
            self::assertInstanceOf(ManagerRegistry::class, $registry);
            self::assertNull($registry->getManagerForClass(AuditEntry::class));

            $entityManager = $testContainer->get('doctrine.orm.entity_manager');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $metadataNames = array_map(static fn ($item): string => $item->getName(), $metadata);
            sort($metadataNames);
            self::assertSame([AuditableEntity::class, NonAuditableEntity::class], $metadataNames);

            (new SchemaTool($entityManager))->createSchema($metadata);
            $tables = $entityManager->getConnection()->createSchemaManager()->listTableNames();
            sort($tables);
            self::assertSame(['test_auditable_entity', 'test_non_auditable_entity'], $tables);

            $consumer = $kernel->getContainer()->get(TransactionalRecorderConsumer::class);
            self::assertInstanceOf(TransactionalRecorderConsumer::class, $consumer);
            $consumer->record(new AuditEvent(
                action: 'mapping-opt-out',
                title: 'Transactional recorder without legacy mapping',
                subject: null,
                actor: new AuditActor('test-user', 'david'),
            ));
        } finally {
            $cacheDir = $kernel->getCacheDir();
            $kernel->shutdown();
            restore_exception_handler();
            self::removeDirectory($cacheDir);
        }
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
