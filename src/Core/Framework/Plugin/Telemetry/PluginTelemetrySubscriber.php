<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class PluginTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'emitPluginInstallCountMetric',
        ];
    }

    public function emitPluginInstallCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'plugin.install.count', value: 1));
    }
}
