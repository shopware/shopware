<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge as GaugeMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Gauge::class)]
class GaugeTest extends TestCase
{
    #[DataProvider('provideValidMetricData')]
    public function testGetMetric(Gauge $attribute, object $decorated, GaugeMetric $expectedMetric): void
    {
        $metric = $attribute->getMetric($decorated);
        static::assertInstanceOf(GaugeMetric::class, $metric);
        static::assertEquals($expectedMetric, $metric);
    }

    /**
     * @return array<array{Gauge, object, GaugeMetric}>
     */
    public static function provideValidMetricData(): array
    {
        return [
            'with all values' => [
                new Gauge(
                    name: 'test_gauge',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                    type: Gauge::TYPE_VALUE,
                ),
                new class {},
                new GaugeMetric(
                    name: 'test_gauge',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                ),
            ],
            'with default values' => [
                new Gauge(
                    name: 'default_gauge',
                    value: 3.5,
                ),
                new class {},
                new GaugeMetric(
                    name: 'default_gauge',
                    value: 3.5,
                ),
            ],
            'dynamic value from property' => [
                new Gauge(
                    name: 'dynamic_property_gauge',
                    value: 'getValue',
                    type: Gauge::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new GaugeMetric(
                    name: 'dynamic_property_gauge',
                    value: 15.5,
                ),
            ],
            'dynamic value from method' => [
                new Gauge(
                    name: 'dynamic_method_gauge',
                    value: 'getValue',
                    type: Gauge::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new GaugeMetric(
                    name: 'dynamic_method_gauge',
                    value: 15.5,
                ),
            ],
        ];
    }

    #[DataProvider('provideInvalidMetricData')]
    public function testGetMetricException(Gauge $attribute, object $decorated): void
    {
        static::expectException(InvalidMetricValueException::class);
        $attribute->getMetric($decorated);
    }

    /**
     * @return array<array{Gauge, object}>
     */
    public static function provideInvalidMetricData(): array
    {
        return [
            'null value from property' => [
                new Gauge(
                    name: 'null_property_gauge',
                    type: Gauge::TYPE_DYNAMIC,
                    value: 'val',
                ),
                new class {
                    public ?int $val = null;
                },
            ],
            'null value from method' => [
                new Gauge(
                    name: 'null_method_gauge',
                    value: 'getValue',
                    type: Gauge::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): null
                    {
                        return null;
                    }
                },
            ],
        ];
    }
}
