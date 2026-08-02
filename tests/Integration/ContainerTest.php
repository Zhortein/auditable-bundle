<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\AuditableBundle\DependencyInjection\ZhorteinAuditableExtension;
use Zhortein\AuditableBundle\Doctrine\AuditableDoctrineListener;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\Historizer;
use Zhortein\AuditableBundle\Service\SecurityActorResolver;
use Zhortein\AuditableBundle\Service\SyncAuditEntryWriter;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class ContainerTest extends TestCase
{
    public function testApplicationDeclaresRequiredBundles(): void
    {
        $bundles = iterator_to_array((new TestKernel())->registerBundles());

        self::assertContainsOnlyInstancesOf(FrameworkBundle::class, [$bundles[0]]);
        self::assertContainsOnlyInstancesOf(DoctrineBundle::class, [$bundles[2]]);
        self::assertContainsOnlyInstancesOf(ZhorteinAuditableBundle::class, [$bundles[3]]);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('writerAliases')]
    public function testLegacyDefinitionsAliasesAndParameters(array $config, string $writer): void
    {
        $container = new ContainerBuilder();
        (new ZhorteinAuditableExtension())->load([$config], $container);

        self::assertTrue($container->hasDefinition(Historizer::class));
        self::assertTrue($container->hasDefinition(AuditableDoctrineListener::class));
        self::assertSame(SecurityActorResolver::class, (string) $container->getAlias(ActorResolverInterface::class));
        // Symfony 7.4's PHP configurator public() has no boolean argument: the historical
        // public(false) call therefore makes this alias public instead of private.
        self::assertTrue($container->getAlias(ActorResolverInterface::class)->isPublic());
        self::assertSame($writer, (string) $container->getAlias(AuditEntryWriterInterface::class));
        self::assertFalse($container->getAlias(AuditEntryWriterInterface::class)->isPublic());
        self::assertSame([], $container->getDefinition(AsyncAuditEntryWriter::class)->getArguments());
        self::assertFalse($container->getDefinition(Historizer::class)->isPublic());
        self::assertFalse($container->getDefinition(AuditableDoctrineListener::class)->isPublic());

        self::assertTrue($container->getParameter('zhortein_auditable.enabled'));
        self::assertSame($config['async']['enabled'] ?? true, $container->getParameter('zhortein_auditable.async.enabled'));
        self::assertSame('async', $container->getParameter('zhortein_auditable.async.transport'));
        self::assertTrue($container->getParameter('zhortein_auditable.listener.track_insert'));
        self::assertTrue($container->getParameter('zhortein_auditable.listener.track_update'));
        self::assertTrue($container->getParameter('zhortein_auditable.listener.track_delete'));
        self::assertSame(180, $container->getParameter('zhortein_auditable.fields.max_string_length'));
        self::assertSame([], $container->getParameter('zhortein_auditable.fields.global_ignored'));
    }

    /** @return iterable<string, array{array<string, mixed>, class-string<AuditEntryWriterInterface>}> */
    public static function writerAliases(): iterable
    {
        yield 'async by default' => [[], AsyncAuditEntryWriter::class];
        yield 'sync when disabled' => [['async' => ['enabled' => false]], SyncAuditEntryWriter::class];
    }
}
