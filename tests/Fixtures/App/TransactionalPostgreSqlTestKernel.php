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
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\ApplicationAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\DoctrineAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\FailingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\PostgreSqlFrozenClock;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\PostgreSqlWiringPass;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring\TransactionalRecorderConsumer;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class TransactionalPostgreSqlTestKernel extends Kernel
{
    public const NORMAL = 'postgresql_transactional';
    public const FAILING_STORAGE = 'postgresql_transactional_failing_storage';

    private readonly string $databaseUrl;

    public function __construct(string $environment)
    {
        $databaseUrl = getenv('TEST_DATABASE_URL');
        if (false === $databaseUrl || '' === trim($databaseUrl)) {
            throw new \RuntimeException('TEST_DATABASE_URL must provide the PostgreSQL test connection.');
        }
        $this->databaseUrl = $databaseUrl;

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

        $container->addCompilerPass(new PostgreSqlWiringPass(self::FAILING_STORAGE === $this->environment));
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $databaseUrl = $this->databaseUrl;
        $loader->load(static function (ContainerBuilder $container) use ($databaseUrl): void {
            $ormConfig = [
                'mappings' => [
                    'TransactionalPostgreSqlFixtures' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__).'/Transactional/PostgreSql/Entity',
                        'prefix' => 'Zhortein\\AuditableBundle\\Tests\\Fixtures\\Transactional\\PostgreSql\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ];
            if (InstalledVersions::satisfies(new VersionParser(), 'doctrine/doctrine-bundle', '^2.0')) {
                $ormConfig['auto_generate_proxy_classes'] = true;
            }

            $container->loadFromExtension('framework', [
                'secret' => 'transactional-postgresql-tests',
                'test' => true,
                'messenger' => ['default_bus' => 'messenger.bus.default'],
            ]);
            $container->loadFromExtension('security', [
                'providers' => ['users' => ['memory' => null]],
                'firewalls' => ['test' => ['security' => false]],
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => $databaseUrl],
                'orm' => $ormConfig,
            ]);
            $container->loadFromExtension('zhortein_auditable', [
                'transactional' => ['enabled' => true],
            ]);

            $container->register(ApplicationAuditEntryFactory::class);
            $container->register(DoctrineAuditStorage::class)->setAutowired(true);
            $container->register(FailingAuditStorage::class);
            $container->register(PostgreSqlFrozenClock::class);
            $container
                ->register(TransactionalRecorderConsumer::class)
                ->setAutowired(true)
                ->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/auditable-bundle-postgresql-tests/'.getmypid().'-'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }
}
