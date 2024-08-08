<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram as HistogramMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Histogram::class)]
class HistogramTest extends TestCase
{
    #[DataProvider('provideValidMetricData')]
    public function testGetMetric(Histogram $attribute, object $decorated, HistogramMetric $expectedMetric): void
    {
        $metric = $attribute->getMetric($decorated);
        static::assertInstanceOf(HistogramMetric::class, $metric);
        static::assertEquals($expectedMetric, $metric);
    }

    /**
     * @return array<array{Histogram, object, HistogramMetric}>
     */
    public static function provideValidMetricData(): array
    {
        return [
            'with all values' => [
                new Histogram(
                    name: 'test_histogram',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                    type: Histogram::TYPE_VALUE,
                ),
                new class {},
                new HistogramMetric(
                    name: 'test_histogram',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                ),
            ],
            'with default values' => [
                new Histogram(
                    name: 'default_histogram',
                    value: 3.5,
                ),
                new class {},
                new HistogramMetric(
                    name: 'default_histogram',
                    value: 3.5,
                ),
            ],
            'dynamic value from property' => [
                new Histogram(
                    name: 'dynamic_property_histogram',
                    value: 'getValue',
                    type: Histogram::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new HistogramMetric(
                    name: 'dynamic_property_histogram',
                    value: 15.5,
                ),
            ],
            'dynamic value from method' => [
                new Histogram(
                    name: 'dynamic_method_histogram',
                    value: 'getValue',
                    type: Histogram::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new HistogramMetric(
                    name: 'dynamic_method_histogram',
                    value: 15.5,
                ),
            ],
        ];
    }

    #[DataProvider('provideInvalidMetricData')]
    public function testGetMetricException(Histogram $attribute, object $decorated): void
    {
        static::expectException(InvalidMetricValueException::class);
        $attribute->getMetric($decorated);
    }

    /**
     * @return array<array{Histogram, object}>
     */
    public static function provideInvalidMetricData(): array
    {
        return [
            'null value from property' => [
                new Histogram(
                    name: 'null_property_histogram',
                    type: Histogram::TYPE_DYNAMIC,
                    value: 'val',
                ),
                new class {
                    public ?int $val = null;
                },
            ],
            'null value from method' => [
                new Histogram(
                    name: 'null_method_histogram',
                    value: 'getValue',
                    type: Histogram::TYPE_DYNAMIC,
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
