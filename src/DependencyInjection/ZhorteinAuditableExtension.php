<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ZhorteinAuditableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('zhortein_auditable.enabled', $config['enabled']);
        $container->setParameter('zhortein_auditable.async.enabled', $config['async']['enabled']);
        $container->setParameter('zhortein_auditable.async.transport', $config['async']['transport']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');
    }
}
