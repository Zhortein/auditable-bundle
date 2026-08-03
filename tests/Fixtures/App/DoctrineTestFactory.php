<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\App;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;

final class DoctrineTestFactory
{
    public static function createEntityManager(): EntityManagerInterface
    {
        return self::createForPaths([
            \dirname(__DIR__, 3).'/src/Entity',
            \dirname(__DIR__).'/Entity',
        ]);
    }

    public static function createTransactionalEntityManager(): EntityManagerInterface
    {
        return self::createForPaths([
            \dirname(__DIR__).'/Transactional/Entity',
        ]);
    }

    /** @param list<string> $paths */
    private static function createForPaths(array $paths): EntityManagerInterface
    {
        $eventManager = new EventManager();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $configuration = ORMSetup::createAttributeMetadataConfiguration($paths, true);

        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        return new EntityManager($connection, $configuration, $eventManager);
    }
}
