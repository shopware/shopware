<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\UnitConverter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\ConvertedUnit;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\MeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\UnitProvider\AbstractMeasurementUnitProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MeasurementUnitConverter::class)]
class MeasurementUnitConverterTest extends TestCase
{
    private AbstractMeasurementUnitProvider&MockObject $unitProvider;

    private MeasurementUnitConverter $converter;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(AbstractMeasurementUnitProvider::class);
        $this->converter = new MeasurementUnitConverter($this->unitProvider);
    }

    public function testConvertSameUnit(): void
    {
        $result = $this->converter->convert(10.5, 'mm', 'mm');

        static::assertSame(10.5, $result->value);
        static::assertSame('mm', $result->unit);
    }

    public function testConvertDifferentUnits(): void
    {
        $fromUnitInfo = [
            'type' => 'length',
            'factor' => 1.0,
            'precision' => 2,
        ];

        $toUnitInfo = [
            'type' => 'length',
            'factor' => 10.0,
            'precision' => 1,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['cm', $toUnitInfo],
            ]);

        $result = $this->converter->convert(100.0, 'mm', 'cm');

        static::assertSame(10.0, $result->value);
        static::assertSame('cm', $result->unit);
    }

    public function testConvertWithCustomPrecision(): void
    {
        $fromUnitInfo = [
            'type' => 'length',
            'factor' => 1.0,
            'precision' => 2,
        ];

        $toUnitInfo = [
            'type' => 'length',
            'factor' => 3.0,
            'precision' => 1,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['custom', $toUnitInfo],
            ]);

        $result = $this->converter->convert(10.0, 'mm', 'custom', 3);

        static::assertSame(3.333, $result->value);
        static::assertSame('custom', $result->unit);
    }

    public function testConvertWithTargetUnitPrecision(): void
    {
        $fromUnitInfo = [
            'type' => 'weight',
            'factor' => 1000.0,
            'precision' => 3,
        ];

        $toUnitInfo = [
            'type' => 'weight',
            'factor' => 1.0,
            'precision' => 0,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['kg', $fromUnitInfo],
                ['g', $toUnitInfo],
            ]);

        $result = $this->converter->convert(1.2345, 'kg', 'g');

        static::assertSame(1235.0, $result->value); // rounded to 0 decimal places
        static::assertSame('g', $result->unit);
    }

    public function testConvertIncompatibleUnits(): void
    {
        $fromUnitInfo = [
            'type' => 'length',
            'factor' => 1.0,
            'precision' => 2,
        ];

        $toUnitInfo = [
            'type' => 'weight',
            'factor' => 1.0,
            'precision' => 2,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['kg', $toUnitInfo],
            ]);

        static::expectException(MeasurementSystemException::class);
        static::expectExceptionMessage('The measurement units "mm" and "kg" are incompatible.');

        $this->converter->convert(10.0, 'mm', 'kg');
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->converter->getDecorated();
    }

    public function testConvertComplexCalculation(): void
    {
        $fromUnitInfo = [
            'type' => 'length',
            'factor' => 0.001, // mm to m
            'precision' => 2,
        ];

        $toUnitInfo = [
            'type' => 'length',
            'factor' => 0.01, // cm to m
            'precision' => 3,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['cm', $toUnitInfo],
            ]);

        // 1250mm * 0.001 / 0.01 = 1250 * 0.1 = 125cm
        $result = $this->converter->convert(1250.0, 'mm', 'cm');

        static::assertSame(125.0, $result->value);
        static::assertSame('cm', $result->unit);
    }

    public function testConvertZeroValue(): void
    {
        $fromUnitInfo = [
            'type' => 'length',
            'factor' => 1.0,
            'precision' => 2,
        ];

        $toUnitInfo = [
            'type' => 'length',
            'factor' => 10.0,
            'precision' => 2,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['cm', $toUnitInfo],
            ]);

        $result = $this->converter->convert(0.0, 'mm', 'cm');

        static::assertSame(0.0, $result->value);
        static::assertSame('cm', $result->unit);
    }

    public function testConvertNegativeValue(): void
    {
        $fromUnitInfo = [
            'type' => 'temperature',
            'factor' => 1.0,
            'precision' => 1,
        ];

        $toUnitInfo = [
            'type' => 'temperature',
            'factor' => 2.0,
            'precision' => 1,
        ];

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['celsius', $fromUnitInfo],
                ['kelvin', $toUnitInfo],
            ]);

        $result = $this->converter->convert(-10.0, 'celsius', 'kelvin');

        static::assertSame(-5.0, $result->value);
        static::assertSame('kelvin', $result->unit);
    }
}
