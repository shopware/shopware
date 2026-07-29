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
#[Package('framework')]
class MetricTest extends TestCase
{
    public function testFromConfiguredMapsAllFields(): void
    {
        $metricConfig = MetricConfig::fromDefinition('test_metric', [
            'type' => Type::HISTOGRAM->value,
            'description' => 'Cache hits',
            'unit' => 'hits',
            'enabled' => true,
            'labels' => [],
        ]);

        $configuredMetric = new ConfiguredMetric('my_metric', 42.5, ['env' => 'stale']);
        $processedLabels = ['env' => 'prod'];
        $metric = Metric::fromConfigured($configuredMetric, $metricConfig, $processedLabels);

        static::assertSame('my_metric', $metric->name);
        static::assertSame(42.5, $metric->value);
        static::assertSame(['env' => 'prod'], $metric->labels, 'Resulting Metric labels differ from provided processedLabels');
        static::assertSame(Type::HISTOGRAM, $metric->type);
        static::assertSame('Cache hits', $metric->description);
        static::assertSame('hits', $metric->unit);
    }

    public function testFromConfiguredResolvesClosureValue(): void
    {
        $metricConfig = MetricConfig::fromDefinition('lazy_metric', [
            'description' => 'Cache hits',
            'type' => Type::HISTOGRAM->value,
            'enabled' => true,
        ]);

        $configuredMetric = new ConfiguredMetric('lazy_metric', static fn () => 99);
        $metric = Metric::fromConfigured($configuredMetric, $metricConfig, []);

        static::assertSame(99, $metric->value);
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
