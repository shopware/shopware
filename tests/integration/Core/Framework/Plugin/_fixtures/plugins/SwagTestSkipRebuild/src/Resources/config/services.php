<?php declare(strict_types=1);

use SwagTestSkipRebuild\SwagTestSkipRebuild;
use SwagTestSkipRebuild\SwagTestSkipRebuildSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SwagTestSkipRebuild::class)
        ->call('manualSetter', [service('category.repository')]);

    $services->set(SwagTestSkipRebuildSubscriber::class)
        ->tag('kernel.event_subscriber');
};
