<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Telemetry\PluginTelemetrySubscriber;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(PluginTelemetrySubscriber::class)]
class PluginTelemetrySubscriberTest extends TestCase
{
    public function testEmitPluginInstallCountMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects(static::once())
            ->method('emit')
            ->with(static::callback(function (ConfiguredMetric $metric) {
                return $metric->name === 'plugin.install.count' && $metric->value === 1;
            }));

        $subscriber = new PluginTelemetrySubscriber($meter);
        $subscriber->emitPluginInstallCountMetric();
    }
}
