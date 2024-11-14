<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[CoversClass(Metric::class)]
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

        $metric = Metric::fromConfigured($configuredMetric, $metricConfig);
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

        $metric = Metric::fromConfigured($configuredMetric, $metricConfig);
        static::assertSame([], $metric->labels);
    }

    public function testFromArray(): void
    {
        $metric = Metric::fromArray([
            'name' => 'test_metric',
            'value' => 100,
            'labels' => ['label1' => 'allowed_value', 'label2' => 'disallowed_value'],
            'type' => Type::COUNTER,
            'description' => 'Cache hits',
        ]);

        static::assertSame('test_metric', $metric->name);
        static::assertSame(100, $metric->value);
        static::assertSame(['label1' => 'allowed_value', 'label2' => 'disallowed_value'], $metric->labels);
        static::assertSame(Type::COUNTER, $metric->type);
        static::assertSame('Cache hits', $metric->description);
        static::assertNull($metric->unit);
    }
}
