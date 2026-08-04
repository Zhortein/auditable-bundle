<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
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

        $mappingPass = DoctrineOrmMappingsPass::createAttributeMappingDriver(
            // Namespaces / mapping prefixes
            ['Zhortein\\AuditableBundle\\Entity'],
            // Directories that contain the entities
            [$entityDir],
            // Manager parameters: empty = all entity managers
            [],
            // Enabled parameter: preserve the mapping by default, with an explicit opt-out.
            'zhortein_auditable.legacy_mapping.enabled',
        );

        $container->addCompilerPass(
            new readonly class($mappingPass) implements CompilerPassInterface {
                public function __construct(
                    private DoctrineOrmMappingsPass $mappingPass,
                ) {
                }

                public function process(ContainerBuilder $container): void
                {
                    if (true !== $container->getParameter('zhortein_auditable.legacy_mapping.enabled')) {
                        return;
                    }

                    $this->mappingPass->process($container);
                }
            }
        );
    }
}
