<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Metric::class)]
class MetricTest extends TestCase
{
    public function testFromConfigMapsAllFields(): void
    {
        $metricConfig = MetricConfig::fromDefinition('test_metric', [
            'type' => Type::HISTOGRAM->value,
            'description' => 'Cache hits',
            'unit' => 'hits',
            'enabled' => true,
            'labels' => [],
        ]);

        // labels and value arrive pre-resolved: validated by MetricLabelProcessor, closures opened by the Meter
        $metric = Metric::fromConfig($metricConfig, ['env' => 'prod'], 42.5);

        static::assertSame('test_metric', $metric->name);
        static::assertSame(42.5, $metric->value);
        static::assertSame(['env' => 'prod'], $metric->labels);
        static::assertSame(Type::HISTOGRAM, $metric->type);
        static::assertSame('Cache hits', $metric->description);
        static::assertSame('hits', $metric->unit);
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
