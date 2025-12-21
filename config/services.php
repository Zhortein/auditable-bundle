<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Services du bundle ajoutés à l’étape 1 (portage du code).
    // Exemple :
    // $services->set(ChangeDetector::class)->public(false);
};
