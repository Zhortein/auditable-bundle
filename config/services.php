<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Zhortein\AuditableBundle\Doctrine\AuditableDoctrineListener;
use Zhortein\AuditableBundle\Metadata\AuditableMetadataProvider;
use Zhortein\AuditableBundle\MessageHandler\PersistAuditEntryMessageHandler;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AuditEntryPersister;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\ChangeDetector;
use Zhortein\AuditableBundle\Service\Historizer;
use Zhortein\AuditableBundle\Service\SecurityActorResolver;
use Zhortein\AuditableBundle\Service\SyncAuditEntryWriter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Load all services from the bundle.
    // - Entities/Attributes/Enums/Messages are excluded (not services)
    $services
        ->load('Zhortein\\AuditableBundle\\', __DIR__ . '/../src/')
        ->exclude([
            __DIR__ . '/../src/DependencyInjection/',
            __DIR__ . '/../src/Entity/',
            __DIR__ . '/../src/Attribute/',
            __DIR__ . '/../src/Enum/',
            __DIR__ . '/../src/Message/',
        ]);

    $services->set(AuditableMetadataProvider::class);

    $services->set(ChangeDetector::class)
        ->arg('$maxStringLength', param('zhortein_auditable.fields.max_string_length'));

    $services->set(AuditEntryPersister::class);

    $services->set(SyncAuditEntryWriter::class);

    $services->set(Historizer::class)
        ->arg('$enabled', param('zhortein_auditable.enabled'));

    // Actor resolver basé sur SecurityBundle
    $services->set(SecurityActorResolver::class);
    $services->alias(ActorResolverInterface::class, SecurityActorResolver::class)->public(false);

    $services->set(PersistAuditEntryMessageHandler::class);

    $services->set(AuditableDoctrineListener::class)
        ->arg('$enabled', param('zhortein_auditable.enabled'))
        ->arg('$trackInsert', param('zhortein_auditable.listener.track_insert'))
        ->arg('$trackUpdate', param('zhortein_auditable.listener.track_update'))
        ->arg('$trackDelete', param('zhortein_auditable.listener.track_delete'))
        ->arg('$globalIgnoredFields', param('zhortein_auditable.fields.global_ignored'));
};
