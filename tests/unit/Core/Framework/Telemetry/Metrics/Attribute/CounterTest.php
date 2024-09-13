<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter as CounterMetric;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Counter::class)]
class CounterTest extends TestCase
{
    #[DataProvider('provideValidMetricData')]
    public function testGetMetric(Counter $attribute, object $decorated, CounterMetric $expectedMetric): void
    {
        $metric = $attribute->getMetric($decorated);
        static::assertInstanceOf(CounterMetric::class, $metric);
        static::assertEquals($expectedMetric, $metric);
    }

    /**
     * @return array<array{Counter, object, CounterMetric}>
     */
    public static function provideValidMetricData(): array
    {
        return [
            'with all values' => [
                new Counter(
                    name: 'test_counter',
                    value: 7,
                    description: 'Test description',
                    unit: 'MB',
                    type: Counter::TYPE_VALUE,
                ),
                new class {},
                new CounterMetric(
                    name: 'test_counter',
                    value: 7,
                    description: 'Test description',
                    unit: 'MB',
                ),
            ],
            'with default values' => [
                new Counter(
                    name: 'default_counter',
                    value: 3,
                ),
                new class {},
                new CounterMetric(
                    name: 'default_counter',
                    value: 3,
                ),
            ],
            'dynamic value from property' => [
                new Counter(
                    name: 'dynamic_property_counter',
                    value: 'getValue',
                    type: Counter::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.0;
                    }
                },
                new CounterMetric(
                    name: 'dynamic_property_counter',
                    value: 15.0,
                ),
            ],
            'dynamic value from method' => [
                new Counter(
                    name: 'dynamic_method_counter',
                    value: 'getValue',
                    type: Counter::TYPE_DYNAMIC,
                ),
                new class {
                    public function getValue(): float
                    {
                        return 15.0;
                    }
                },
                new CounterMetric(
                    name: 'dynamic_method_counter',
                    value: 15.0,
                ),
            ],
        ];
    }

    #[DataProvider('provideInvalidMetricData')]
    public function testGetMetricException(Counter $attribute, object $decorated): void
    {
        static::expectException(InvalidMetricValueException::class);
        $attribute->getMetric($decorated);
    }

    /**
     * @return array<array{Counter, object}>
     */
    public static function provideInvalidMetricData(): array
    {
        return [
            'null value from property' => [
                new Counter(
                    name: 'null_property_counter',
                    type: Counter::TYPE_DYNAMIC,
                    value: 'val',
                ),
                new class {
                    public ?int $val = null;
                },
            ],
            'null value from method' => [
                new Counter(
                    name: 'null_method_counter',
                    value: 'getValue',
                    type: Counter::TYPE_DYNAMIC,
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
