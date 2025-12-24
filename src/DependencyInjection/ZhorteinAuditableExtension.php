<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\SyncAuditEntryWriter;

final class ZhorteinAuditableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('zhortein_auditable.enabled', (bool) $config['enabled']);

        $container->setParameter('zhortein_auditable.async.enabled', (bool) $config['async']['enabled']);
        $container->setParameter('zhortein_auditable.async.transport', (string) $config['async']['transport']);

        $container->setParameter('zhortein_auditable.listener.track_insert', (bool) $config['listener']['track_insert']);
        $container->setParameter('zhortein_auditable.listener.track_update', (bool) $config['listener']['track_update']);
        $container->setParameter('zhortein_auditable.listener.track_delete', (bool) $config['listener']['track_delete']);

        $container->setParameter('zhortein_auditable.fields.max_string_length', (int) $config['fields']['max_string_length']);
        $container->setParameter('zhortein_auditable.fields.global_ignored', (array) $config['fields']['global_ignored']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        // Alias Writer (sync vs async)
        $asyncEnabled = (bool) $config['async']['enabled'];
        $container->setAlias(
            AuditEntryWriterInterface::class,
            $asyncEnabled ? AsyncAuditEntryWriter::class : SyncAuditEntryWriter::class
        )->setPublic(false);
    }
}
