<?php declare(strict_types=1);

use SwagTestPlugin\SwagTestPlugin;
use SwagTestPlugin\SwagTestSubscriber;
use SwagTestPlugin\SwagTestTask;
use SwagTestPlugin\SwagTestTaskHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SwagTestPlugin::class)
        ->call('manualSetter', [service('category.repository')]);

    $services->set(SwagTestSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(SwagTestTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(SwagTestTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
        ])
        ->tag('messenger.message_handler');
};
