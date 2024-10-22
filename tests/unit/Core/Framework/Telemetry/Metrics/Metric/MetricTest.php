<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Metric;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Metric::class)]
#[Package('core')]
class MetricTest extends TestCase
{
    public function testInvokeRemovesDisallowedLabels(): void
    {
        $metricConfig = MetricConfig::fromDefinition(
            'test_metric',
            [
                'type' => Type::COUNTER->value,
                'description' => 'Cache hits',
                'unit' => 'hits',
                'enabled' => true,
                'labels' => [
                    'label1' => ['allowed_values' => ['allowed_value', 'another_allowed_value']],
                    'label2' => ['allowed_values' => ['allowed_value', 'another_allowed_value']],
                ],
            ]
        );

        $configuredMetric = new ConfiguredMetric(
            'test_metric',
            100,
            ['label1' => 'allowed_value', 'label2' => 'disallowed_value']
        );

        $metric = new Metric($configuredMetric, $metricConfig);
        static::assertSame(['label1' => 'allowed_value'], $metric->labels);
    }

    public function testInvokeWithNoAllowedValues(): void
    {
        $metricConfig = MetricConfig::fromDefinition(
            'test_metric',
            [
                'type' => Type::COUNTER->value,
                'description' => 'Cache hits',
                'unit' => 'hits',
                'enabled' => true,
                'labels' => [],
            ]
        );

        $configuredMetric = new ConfiguredMetric(
            'test_metric',
            100,
            ['some_label' => 'some_value', 'another_label' => 'another_value']
        );

        $metric = new Metric($configuredMetric, $metricConfig);
        static::assertSame([], $metric->labels);
    }
}
