<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Psr\Clock\ClockInterface as PsrClockInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\MetricLabelProcessor;
use Shopware\Core\Framework\Telemetry\Metrics\ScheduledTask\CollectPeriodicMetricsTask;
use Shopware\Core\Framework\Telemetry\Metrics\ScheduledTask\CollectPeriodicMetricsTaskHandler;
use Shopware\Core\Framework\Telemetry\Metrics\Subscriber\TelemetryFlushListener;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\Framework\Telemetry\Tracking\TrackingService;
use Shopware\Core\Framework\Telemetry\Tracking\TrackingTransport;
use Shopware\Core\Framework\Telemetry\Tracking\UdpTrackingTransport;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Meter::class)
        ->public()
        ->lazy()
        ->args([
            service(TransportCollection::class),
            service(MetricConfigProvider::class),
            service(MetricLabelProcessor::class),
            service('logger'),
            env('APP_ENV'),
            param('shopware.telemetry.metrics.enabled'),
        ]);

    $services->set(Telemetry::class)
        ->args([
            service(Meter::class),
            env('APP_ENV'),
        ]);

    $services->set(MetricLabelProcessor::class)
        ->args([
            param('shopware.telemetry.metrics.replace_unknown_label_values_with'),
            service('logger'),
            env('APP_ENV'),
        ]);

    $services->set(MetricConfigProvider::class)
        ->args([
            param('shopware.telemetry.metrics.definitions'),
        ]);

    $services->set(TransportConfigProvider::class)
        ->args([
            service(MetricConfigProvider::class),
            param('shopware.telemetry.metrics.namespace'),
        ]);

    $services->set(TransportCollection::class)
        ->lazy()
        ->factory([TransportCollection::class, 'create'])
        ->args([
            tagged_iterator('shopware.metric_transport_factory'),
            service(TransportConfigProvider::class),
        ]);

    $services->set(CollectPeriodicMetricsTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(TelemetryFlushListener::class)
        ->args([
            service(TransportCollection::class),
            service('logger'),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');

    $services->set(CollectPeriodicMetricsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Meter::class),
            tagged_iterator('shopware.telemetry.periodic_metric_collector'),
        ])
        ->tag('messenger.message_handler');

    $services->set(UdpTrackingTransport::class);

    $services->alias(TrackingTransport::class, UdpTrackingTransport::class);

    $services->set(TrackingService::class)
        ->args([
            service(SystemConfigService::class),
            service(TrackingTransport::class),
            service(PsrClockInterface::class),
            param('kernel.shopware_version'),
        ]);
};
