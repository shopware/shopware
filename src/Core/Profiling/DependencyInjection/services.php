<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DependencyInjection;

use Shopware\Core\Framework\Adapter\Command\CacheWatchDelayedCommand;
use Shopware\Core\Profiling\Integration\Datadog;
use Shopware\Core\Profiling\Integration\ServerTiming;
use Shopware\Core\Profiling\Integration\Stopwatch;
use Shopware\Core\Profiling\Integration\Tideways;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Stopwatch::class)
        ->args([
            service('debug.stopwatch')->nullOnInvalid(),
        ])
        ->tag('shopware.profiler', ['integration' => 'Symfony']);

    $services->set(Tideways::class)
        ->tag('shopware.profiler', ['integration' => 'Tideways']);

    $services->set(CacheWatchDelayedCommand::class)
        ->tag('console.command')
        ->args([
            service('service_container'),
        ]);

    $services->set(Datadog::class)
        ->tag('shopware.profiler', ['integration' => 'Datadog']);

    $services->set(ServerTiming::class)
        ->tag('shopware.profiler', ['integration' => 'ServerTiming'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponseEvent']);

    $services->set(Profiler::class)
        ->public()
        ->args([
            tagged_iterator('shopware.profiler', 'integration'),
            param('shopware.profiler.integrations'),
        ]);
};
