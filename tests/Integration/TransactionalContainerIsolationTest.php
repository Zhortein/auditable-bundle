<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zhortein\AuditableBundle\DependencyInjection\ZhorteinAuditableExtension;
use Zhortein\AuditableBundle\Doctrine\AuditableDoctrineListener;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryPersister;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\SecurityActorResolver;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Exception\ActorResolutionException;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\DoctrineIdentifierExtractor;
use Zhortein\AuditableBundle\Transactional\Service\StrictAuditRecorder;
use Zhortein\AuditableBundle\Transactional\Service\SymfonySecurityActorResolver;

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
            foreach ([AuditRecorderInterface::class, AuditEntryFactoryInterface::class, AuditStorageInterface::class] as $contract) {
                self::assertTrue(interface_exists($contract));
                self::assertFalse($container->has($contract));
            }
            self::assertTrue(interface_exists(IdentifierExtractorInterface::class));
            self::assertFalse($container->has(IdentifierExtractorInterface::class));
            self::assertFalse($container->has(DoctrineIdentifierExtractor::class));
            self::assertFalse($container->has(SymfonySecurityActorResolver::class));
            self::assertTrue(class_exists(StrictAuditRecorder::class));
            self::assertFalse($container->has(StrictAuditRecorder::class));
            self::assertTrue(class_exists(ActorResolutionException::class));
            self::assertFalse($container->has(ActorResolutionException::class));
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
        self::assertSame(SymfonySecurityActorResolver::class, (string) $container->getAlias(AuditActorResolverInterface::class));
        self::assertFalse($container->getAlias(AuditActorResolverInterface::class)->isPublic());
        self::assertTrue($container->hasDefinition(SymfonySecurityActorResolver::class));
        self::assertFalse($container->getDefinition(SymfonySecurityActorResolver::class)->isPublic());
        foreach ([AuditRecorderInterface::class, AuditEntryFactoryInterface::class, AuditStorageInterface::class] as $contract) {
            self::assertFalse($container->hasAlias($contract));
        }
        self::assertFalse($container->hasDefinition(StrictAuditRecorder::class));
        self::assertFalse($container->hasAlias(ClockInterface::class));
        self::assertTrue($container->hasParameter('zhortein_auditable.legacy_mapping.enabled'));
        self::assertTrue($container->getParameter('zhortein_auditable.legacy_mapping.enabled'));
        self::assertNoTransactionalParameters($container);
    }

    public function testTransactionalRecorderDefinitionIsConditionalAndPrivateBeforeCompilation(): void
    {
        $container = new ContainerBuilder();
        (new ZhorteinAuditableExtension())->load([['transactional' => ['enabled' => true]]], $container);

        self::assertTrue($container->hasDefinition(StrictAuditRecorder::class));
        $definition = $container->getDefinition(StrictAuditRecorder::class);
        self::assertSame(StrictAuditRecorder::class, $definition->getClass());
        self::assertTrue($definition->isAutowired());
        self::assertFalse($definition->isAutoconfigured());
        self::assertFalse($definition->isPublic());
        self::assertTrue($definition->isShared());
        self::assertSame([], $definition->getArguments());
        self::assertSame([], $definition->getTags());

        self::assertTrue($container->hasAlias(AuditRecorderInterface::class));
        $alias = $container->getAlias(AuditRecorderInterface::class);
        self::assertSame(StrictAuditRecorder::class, (string) $alias);
        self::assertFalse($alias->isPublic());

        foreach ([AuditEntryFactoryInterface::class, AuditStorageInterface::class, ClockInterface::class] as $contract) {
            self::assertFalse($container->hasAlias($contract));
        }
        self::assertNoTransactionalParameters($container);

        $container->setAlias(IdentifierExtractorInterface::class, 'app.identifier_extractor')->setPublic(false);
        $container->setAlias(AuditActorResolverInterface::class, 'app.actor_resolver')->setPublic(false);
        self::assertSame('app.identifier_extractor', (string) $container->getAlias(IdentifierExtractorInterface::class));
        self::assertSame('app.actor_resolver', (string) $container->getAlias(AuditActorResolverInterface::class));
    }

    public function testLegacyMappingOptOutParameterIsFalseBeforeCompilation(): void
    {
        $container = new ContainerBuilder();
        (new ZhorteinAuditableExtension())->load([[
            'enabled' => false,
            'legacy_mapping' => ['enabled' => false],
        ]], $container);

        self::assertTrue($container->hasParameter('zhortein_auditable.legacy_mapping.enabled'));
        self::assertFalse($container->getParameter('zhortein_auditable.legacy_mapping.enabled'));
        self::assertFalse($container->getParameter('zhortein_auditable.enabled'));
        self::assertTrue($container->hasDefinition(AuditEntryPersister::class));
        self::assertTrue($container->hasDefinition(AuditableDoctrineListener::class));
        self::assertSame(AsyncAuditEntryWriter::class, (string) $container->getAlias(AuditEntryWriterInterface::class));
        self::assertSame(SecurityActorResolver::class, (string) $container->getAlias(ActorResolverInterface::class));
        self::assertFalse($container->hasAlias(AuditEntryFactoryInterface::class));
        self::assertFalse($container->hasAlias(AuditStorageInterface::class));
        self::assertFalse($container->hasAlias(ClockInterface::class));
    }

    public function testLegacyMappingCannotBeDisabledForAnActiveLegacyRuntime(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The legacy Doctrine mapping cannot be disabled while legacy auditing is enabled. Set "enabled" to false first.');

        (new ZhorteinAuditableExtension())->load([[
            'enabled' => true,
            'legacy_mapping' => ['enabled' => false],
        ]], $container);
    }

    private static function assertNoTransactionalParameters(ContainerBuilder $container): void
    {
        $parameters = array_filter(
            array_keys($container->getParameterBag()->all()),
            static fn (int|string $name): bool => str_starts_with((string) $name, 'zhortein_auditable.transactional.'),
        );
        self::assertSame([], $parameters);
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
