<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\App;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\FrozenClock;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalWiringPass;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class TransactionalNoLegacyMappingTestKernel extends Kernel
{
    private const NOW = '2026-08-03T10:15:30+02:00';

    public function __construct()
    {
        parent::__construct('transactional_without_legacy_mapping', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new ZhorteinAuditableBundle();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TransactionalWiringPass(true, true, true));
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $ormConfig = [
                'mappings' => [
                    'TestFixtures' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__).'/Entity',
                        'prefix' => 'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ];
            if (InstalledVersions::satisfies(new VersionParser(), 'doctrine/doctrine-bundle', '^2.0')) {
                $ormConfig['auto_generate_proxy_classes'] = true;
            }

            $container->loadFromExtension('framework', [
                'secret' => 'transactional-no-legacy-mapping-tests',
                'test' => true,
                'messenger' => ['default_bus' => 'messenger.bus.default'],
            ]);
            $container->loadFromExtension('security', [
                'providers' => ['users' => ['memory' => null]],
                'firewalls' => ['test' => ['security' => false]],
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => 'sqlite:///:memory:'],
                'orm' => $ormConfig,
            ]);
            $container->loadFromExtension('zhortein_auditable', [
                'enabled' => false,
                'legacy_mapping' => ['enabled' => false],
                'transactional' => ['enabled' => true],
            ]);

            $container->register(CapturingAuditEntryFactory::class);
            $container->register(CapturingAuditStorage::class);
            $container->register(FrozenClock::class)
                ->setArguments([new Definition(\DateTimeImmutable::class, [self::NOW])]);
            $container->register(TransactionalRecorderConsumer::class)
                ->setAutowired(true)
                ->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/auditable-bundle-no-legacy-mapping-tests/'.getmypid();
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
