<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class AppTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'emitAppInstalledMetric',
        ];
    }

    public function emitAppInstalledMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'app.install.count', value: 1));
    }
}
