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
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CallSequence;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\FrozenClock;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalWiringPass;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class TransactionalWiringTestKernel extends Kernel
{
    public const ENABLED = 'transactional_enabled';
    public const MISSING_FACTORY = 'transactional_missing_factory';
    public const MISSING_STORAGE = 'transactional_missing_storage';
    public const MISSING_CLOCK = 'transactional_missing_clock';

    private const NOW = '2026-08-03T10:15:30+02:00';

    public function __construct(string $environment)
    {
        parent::__construct($environment, true);
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

        $container->addCompilerPass(new TransactionalWiringPass(
            provideFactory: self::MISSING_FACTORY !== $this->environment,
            provideStorage: self::MISSING_STORAGE !== $this->environment,
            provideClock: self::MISSING_CLOCK !== $this->environment,
        ));
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
                'secret' => 'transactional-wiring-tests',
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
                'transactional' => ['enabled' => true],
            ]);

            $container->register(CallSequence::class)->setPublic(true);
            $container
                ->register(CapturingAuditEntryFactory::class)
                ->setAutowired(true);
            $container
                ->register(CapturingAuditStorage::class)
                ->setAutowired(true)
                ->setPublic(true);
            $container
                ->register(FrozenClock::class)
                ->setArguments([new Definition(\DateTimeImmutable::class, [self::NOW])])
                ->setAutowired(true);
            $container
                ->register(TransactionalRecorderConsumer::class)
                ->setAutowired(true)
                ->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/auditable-bundle-transactional-tests/'.getmypid().'-'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
