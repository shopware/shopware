<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AppTelemetrySubscriber::class)]
class AppTelemetrySubscriberTest extends TestCase
{
    public function testEmitAppInstalledMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (ConfiguredMetric $metric) {
                return $metric->name === 'app.install.count' && $metric->value === 1;
            }));

        $subscriber = new AppTelemetrySubscriber($meter);
        $subscriber->emitAppInstalledMetric();
    }
}
