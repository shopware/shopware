<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MetricConfigProvider::class)]
class MetricConfigProviderTest extends TestCase
{
    public function testGetReturnsCorrectMetricConfig(): void
    {
        $definitions = [
            'test_metric' => [
                'type' => Type::COUNTER->value,
                'description' => 'Test metric description',
                'enabled' => true,
                'labels' => [
                    'sale_channel' => [
                        'allowed_values' => ['web', 'mobile', 'api'],
                    ],
                ],
            ],
        ];

        $provider = new MetricConfigProvider($definitions);
        $metricConfig = $provider->get('test_metric');

        static::assertSame('Test metric description', $metricConfig->description);
        static::assertSame(Type::COUNTER, $metricConfig->type);
        static::assertTrue($metricConfig->enabled);
        static::assertArrayHasKey('sale_channel', $metricConfig->labels);
        static::assertSame(['web', 'mobile', 'api'], $metricConfig->labels['sale_channel']['allowed_values']);
    }

    public function testGetReturnsNullForUnknownMetric(): void
    {
        $this->expectException(MissingMetricConfigurationException::class);
        $provider = new MetricConfigProvider([]);
        $provider->get('unknown_metric');
    }

    public function testAllReturnsAllMetricConfigs(): void
    {
        $definitions = [
            'metric_one' => [
                'type' => Type::COUNTER->value,
                'description' => 'Metric one description',
                'enabled' => true,
            ],
            'metric_two' => [
                'type' => Type::GAUGE->value,
                'description' => 'Metric two description',
                'enabled' => false,
            ],
        ];

        $provider = new MetricConfigProvider($definitions);
        $allMetrics = $provider->all();

        static::assertCount(2, $allMetrics);
        static::assertSame('Metric one description', $allMetrics[0]->description);
        static::assertSame('Metric two description', $allMetrics[1]->description);
    }
}
