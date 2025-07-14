<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ConvertedUnit::class)]
class ConvertedUnitTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $value = 12.5;
        $unit = 'kg';

        $convertedUnit = new ConvertedUnit($value, $unit);

        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }

    public function testConstructorWithZeroValue(): void
    {
        $value = 0.0;
        $unit = 'cm';

        $convertedUnit = new ConvertedUnit($value, $unit);

        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }

    public function testConstructorWithNegativeValue(): void
    {
        $value = -10.75;
        $unit = 'celsius';

        $convertedUnit = new ConvertedUnit($value, $unit);

        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }

    public function testConstructorWithLargeValue(): void
    {
        $value = 9999999.999999;
        $unit = 'mm';

        $convertedUnit = new ConvertedUnit($value, $unit);

        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }

    public function testConstructorWithEmptyUnit(): void
    {
        $value = 5.0;
        $unit = '';

        $convertedUnit = new ConvertedUnit($value, $unit);

        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }

    public function testReadonlyProperties(): void
    {
        $value = 42.42;
        $unit = 'kg';

        $convertedUnit = new ConvertedUnit($value, $unit);

        // These should be readonly and not modifiable
        static::assertSame($value, $convertedUnit->value);
        static::assertSame($unit, $convertedUnit->unit);
    }
}
