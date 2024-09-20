<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Telemetry\CacheTelemetrySubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(CacheTelemetrySubscriber::class)]
class CacheTelemetrySubscriberTest extends TestCase
{
    public function testEmitInvalidateCacheCountMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (ConfiguredMetric $metric) {
                return $metric->name === 'cache.invalidate.count' && $metric->value === 1;
            }));

        $subscriber = new CacheTelemetrySubscriber($meter);
        $subscriber->emitInvalidateCacheCountMetric();
    }
}
