<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zhortein\AuditableBundle\DependencyInjection\ZhorteinAuditableExtension;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\SecurityActorResolver;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\DoctrineIdentifierExtractor;

final class TransactionalContainerIsolationTest extends TestCase
{
    public function testTransactionalVocabularyIsIsolatedFromRuntimeContainerAndDoctrine(): void
    {
        $kernel = new TestKernel();
        self::removeDirectory($kernel->getCacheDir());
        $kernel->boot();
        try {
            $container = $kernel->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $testContainer = $container->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);

            foreach ([AuditSubject::class, AuditActor::class, AuditEvent::class, AuditRecord::class] as $model) {
                self::assertTrue(class_exists($model));
                self::assertFalse($container->has($model));
            }
            foreach ([AuditRecorderInterface::class, AuditActorResolverInterface::class, AuditEntryFactoryInterface::class, AuditStorageInterface::class] as $contract) {
                self::assertTrue(interface_exists($contract));
                self::assertFalse($container->has($contract));
            }
            self::assertTrue(interface_exists(IdentifierExtractorInterface::class));
            self::assertFalse($container->has(IdentifierExtractorInterface::class));
            self::assertFalse($container->has(DoctrineIdentifierExtractor::class));
            self::assertTrue(class_exists(IdentifierExtractionException::class));
            self::assertFalse($container->has(IdentifierExtractionException::class));

            self::assertInstanceOf(AsyncAuditEntryWriter::class, $testContainer->get(AuditEntryWriterInterface::class));
            self::assertInstanceOf(SecurityActorResolver::class, $testContainer->get(ActorResolverInterface::class));

            $transactionalParameters = array_filter(
                array_keys($container->getParameterBag()->all()),
                static fn (int|string $name): bool => str_contains((string) $name, 'transactional'),
            );
            self::assertSame([], $transactionalParameters);

            $entityManager = $testContainer->get('doctrine.orm.entity_manager');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $classes = array_map(static fn ($classMetadata): string => $classMetadata->getName(), $metadata);
            sort($classes);
            self::assertSame([
                AuditEntry::class,
                'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Entity\\AuditableEntity',
                'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Entity\\NonAuditableEntity',
            ], $classes);
            self::assertSame(['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'], self::sortedTableNames($metadata));
            (new SchemaTool($entityManager))->createSchema($metadata);
            $tables = $entityManager->getConnection()->createSchemaManager()->listTableNames();
            sort($tables);
            self::assertSame(['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'], $tables);
        } finally {
            $cacheDir = $kernel->getCacheDir();
            $kernel->shutdown();
            restore_exception_handler();
            self::removeDirectory($cacheDir);
        }
    }

    public function testNoTransactionalAliasReplacesLegacyAliases(): void
    {
        $container = new ContainerBuilder();
        (new ZhorteinAuditableExtension())->load([], $container);

        self::assertSame(AsyncAuditEntryWriter::class, (string) $container->getAlias(AuditEntryWriterInterface::class));
        self::assertFalse($container->getAlias(AuditEntryWriterInterface::class)->isPublic());
        self::assertSame(SecurityActorResolver::class, (string) $container->getAlias(ActorResolverInterface::class));
        self::assertTrue($container->getAlias(ActorResolverInterface::class)->isPublic());
        self::assertSame(DoctrineIdentifierExtractor::class, (string) $container->getAlias(IdentifierExtractorInterface::class));
        self::assertFalse($container->getAlias(IdentifierExtractorInterface::class)->isPublic());
        self::assertTrue($container->hasDefinition(DoctrineIdentifierExtractor::class));
        self::assertFalse($container->getDefinition(DoctrineIdentifierExtractor::class)->isPublic());
        foreach ([AuditRecorderInterface::class, AuditActorResolverInterface::class, AuditEntryFactoryInterface::class, AuditStorageInterface::class] as $contract) {
            self::assertFalse($container->hasAlias($contract));
        }
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
