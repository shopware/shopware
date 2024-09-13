<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\UpDownCounter;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter as UpDownCounterMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(UpDownCounter::class)]
class UpDownCounterTest extends TestCase
{
    #[DataProvider('provideValidMetricData')]
    public function testGetMetric(UpDownCounter $attribute, object $decorated, UpDownCounterMetric $expectedMetric): void
    {
        $metric = $attribute->getMetric($decorated);
        static::assertInstanceOf(UpDownCounterMetric::class, $metric);
        static::assertEquals($expectedMetric, $metric);
    }

    /**
     * @return array<array{UpDownCounter, object, UpDownCounterMetric}>
     */
    public static function provideValidMetricData(): array
    {
        return [
            'with all values' => [
                new UpDownCounter(
                    name: 'test_updowncounter',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                    type: UpDownCounter::TYPE_VALUE,
                ),
                new class {},
                new UpDownCounterMetric(
                    name: 'test_updowncounter',
                    value: 7.5,
                    description: 'Test description',
                    unit: 'MB',
                ),
            ],
            'with default values' => [
                new UpDownCounter(
                    name: 'default_updowncounter',
                    value: 3.5,
                ),
                new class {},
                new UpDownCounterMetric(
                    name: 'default_updowncounter',
                    value: 3.5,
                ),
            ],
            'dynamic value from property' => [
                new UpDownCounter(
                    name: 'dynamic_property_updowncounter',
                    value: 'getValue',
                    type: UpDownCounter::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new UpDownCounterMetric(
                    name: 'dynamic_property_updowncounter',
                    value: 15.5,
                ),
            ],
            'dynamic value from method' => [
                new UpDownCounter(
                    name: 'dynamic_method_updowncounter',
                    value: 'getValue',
                    type: UpDownCounter::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.5;
                    }
                },
                new UpDownCounterMetric(
                    name: 'dynamic_method_updowncounter',
                    value: 15.5,
                ),
            ],
        ];
    }

    #[DataProvider('provideInvalidMetricData')]
    public function testGetMetricException(UpDownCounter $attribute, object $decorated): void
    {
        static::expectException(InvalidMetricValueException::class);
        $attribute->getMetric($decorated);
    }

    /**
     * @return array<array{UpDownCounter, object}>
     */
    public static function provideInvalidMetricData(): array
    {
        return [
            'null value from property' => [
                new UpDownCounter(
                    name: 'null_property_updowncounter',
                    type: UpDownCounter::TYPE_DYNAMIC,
                    value: 'val',
                ),
                new class {
                    public ?int $val = null;
                },
            ],
            'null value from method' => [
                new UpDownCounter(
                    name: 'null_method_updowncounter',
                    value: 'getValue',
                    type: UpDownCounter::TYPE_DYNAMIC,
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
