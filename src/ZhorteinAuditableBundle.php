<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ZhorteinAuditableBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register bundle entities mapping (History, etc.) without requiring any application config.
        // Uses DoctrineBundle's official compiler pass helper.
        if (!class_exists(DoctrineOrmMappingsPass::class)) {
            return;
        }

        $entityDir = realpath(__DIR__.'/Entity');
        if (false === $entityDir) {
            return;
        }

        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createAttributeMappingDriver(
                // Namespaces / mapping prefixes
                ['Zhortein\\AuditableBundle\\Entity'],
                // Directories that contain the entities
                [$entityDir],
                // Manager parameters: empty = all entity managers
                [],
                // Enabled parameter: false = always enabled
                false,
                // Alias map: keep empty (short namespace aliases are deprecated/removed)
                [],
            )
        );
    }
}
