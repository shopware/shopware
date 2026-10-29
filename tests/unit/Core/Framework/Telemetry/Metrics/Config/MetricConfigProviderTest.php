<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[Package('framework')]
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
        static::assertSame(['web', 'mobile', 'api'], $metricConfig->labels['sale_channel']->allowedValues);
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

    public function testAppValuesAreUnionedIntoExtensibleLabels(): void
    {
        $provider = new MetricConfigProvider($this->extensibleDefinitions(), $this->appLoader([
            'test_metric' => ['payment_method' => ['swag_method', 'core_method']],
        ]));

        $label = $provider->get('test_metric')->labels['payment_method'];

        static::assertSame(['core_method', 'other', 'swag_method'], $label->allowedValues);
    }

    public function testAppValuesForLockedLabelAreIgnored(): void
    {
        $provider = new MetricConfigProvider($this->extensibleDefinitions(), $this->appLoader([
            'test_metric' => ['result' => ['swag_result']],
        ]));

        $label = $provider->get('test_metric')->labels['result'];

        static::assertSame(['success', 'failed'], $label->allowedValues);
    }

    public function testAppValuesForUnknownMetricOrLabelAreIgnored(): void
    {
        $provider = new MetricConfigProvider($this->extensibleDefinitions(), $this->appLoader([
            'unknown_metric' => ['payment_method' => ['v']],
            'test_metric' => ['unknown_label' => ['v']],
        ]));

        $label = $provider->get('test_metric')->labels['payment_method'];

        static::assertSame(['core_method', 'other'], $label->allowedValues);
    }

    /**
     * @return array<string, array{type: 'counter', description: string, enabled: bool, labels: array<string, array{allowed_values?: list<string>, add_allowed_values?: list<string>}>}>
     */
    private function extensibleDefinitions(): array
    {
        return [
            'test_metric' => [
                'type' => Type::COUNTER->value,
                'description' => 'Test metric',
                'enabled' => true,
                'labels' => [
                    'payment_method' => ['add_allowed_values' => ['core_method', 'other']],
                    'result' => ['allowed_values' => ['success', 'failed']],
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string, list<string>>> $labelValues
     *
     * @return \Closure(): AppTelemetryConfigLoader
     */
    private function appLoader(array $labelValues): \Closure
    {
        $loader = static::createStub(AppTelemetryConfigLoader::class);
        $loader->method('load')->willReturn(['labelValues' => $labelValues, 'mappings' => []]);

        return static fn (): AppTelemetryConfigLoader => $loader;
    }
}
