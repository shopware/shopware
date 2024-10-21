<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Telemetry;

use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class CacheTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateCacheEvent::class => 'emitInvalidateCacheCountMetric',
        ];
    }

    public function emitInvalidateCacheCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric('cache.invalidate.count', 1));
    }
}
