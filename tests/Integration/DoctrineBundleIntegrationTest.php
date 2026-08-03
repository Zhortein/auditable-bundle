<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zhortein\AuditableBundle\Doctrine\AuditableDoctrineListener;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;

final class DoctrineBundleIntegrationTest extends TestCase
{
    private TestKernel $kernel;
    private EntityManagerInterface $entityManager;
    private ContainerInterface $testContainer;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel();
        $this->kernel->boot();

        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $this->testContainer = $testContainer;
        $registry = $testContainer->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManagerForClass(AuditEntry::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $this->entityManager = $manager;
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
        $cacheDir = $this->kernel->getCacheDir();
        $this->kernel->shutdown();
        restore_exception_handler();
        self::removeDirectory($cacheDir);
    }

    public function testBundleMappingSchemaAndDoctrineListenerUseDoctrineBundleEntityManager(): void
    {
        self::assertTrue($this->kernel->getContainer()->getParameter('zhortein_auditable.legacy_mapping.enabled'));
        $metadata = $this->entityManager->getClassMetadata(AuditEntry::class);
        self::assertSame(AuditEntry::class, $metadata->getName());
        self::assertSame('audit_entry', $metadata->getTableName());
        self::assertSame([
            'id',
            'occurred_at',
            'action',
            'level',
            'title',
            'description',
            'context',
            'entity_class',
            'entity_id',
            'actor_id',
            'impersonator_id',
            'is_auto',
            'data',
        ], array_values(array_map(static fn ($mapping): string => $mapping->columnName, $metadata->fieldMappings)));

        $indexes = $metadata->table['indexes'] ?? null;
        self::assertIsArray($indexes);
        self::assertSame(['columns' => ['occurred_at']], $indexes['idx_audit_entry_occurred_at']);
        self::assertSame(['columns' => ['entity_class', 'entity_id']], $indexes['idx_audit_entry_entity']);

        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($allMetadata);
        self::assertTrue($this->entityManager->getConnection()->createSchemaManager()->tablesExist(['audit_entry']));

        // test.service_container can retrieve this private alias; that does not alter
        // the visibility asserted directly on the pre-compilation ContainerBuilder.
        self::assertInstanceOf(AsyncAuditEntryWriter::class, $this->testContainer->get(AuditEntryWriterInterface::class));

        foreach ([Events::onFlush, Events::postPersist, Events::preRemove] as $event) {
            $listeners = $this->entityManager->getEventManager()->getListeners($event);
            $listenerFound = false;
            foreach ($listeners as $listener) {
                if ($listener instanceof AuditableDoctrineListener) {
                    $listenerFound = true;
                    break;
                }
            }
            self::assertTrue($listenerFound, \sprintf('The bundle listener is not registered for %s.', $event));
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
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
