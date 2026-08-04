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
use Symfony\Component\HttpKernel\Kernel;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class TestKernel extends Kernel
{
    /** @param array<string, mixed> $bundleConfig */
    public function __construct(
        private readonly array $bundleConfig = [],
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new ZhorteinAuditableBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
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
                'secret' => 'auditable-characterization-tests',
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
            $container->loadFromExtension('zhortein_auditable', $this->bundleConfig);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/auditable-bundle-tests/'.getmypid().'-'.sha1(serialize($this->bundleConfig));
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
