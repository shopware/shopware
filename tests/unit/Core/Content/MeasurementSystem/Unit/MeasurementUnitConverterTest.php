<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitProvider;
use Shopware\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            1.0,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'cm',
            'length',
            10.0,
            1
        );

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            1.0,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'custom',
            'length',
            3.0,
            3
        );

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'kg',
            'weight',
            1000.0,
            3
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'g',
            'weight',
            1.0,
            0
        );

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            1.0,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'kg',
            'weight',
            1.0,
            2
        );

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            0.001,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'cm',
            'length',
            0.01,
            3
        );

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
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            1.0,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'cm',
            'length',
            10.0,
            2
        );

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

    public function testConvertZeroFactor(): void
    {
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            1.0,
            2
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'cm',
            'length',
            0.0,
            2
        );

        $this->unitProvider
            ->expects($this->exactly(2))
            ->method('getUnitInfo')
            ->willReturnMap([
                ['mm', $fromUnitInfo],
                ['cm', $toUnitInfo],
            ]);

        static::expectException(MeasurementSystemException::class);
        static::expectExceptionMessage('The measurement system unit "cm" cannot have a factor of zero.');
        $this->converter->convert(1.0, 'mm', 'cm');
    }

    public function testConvertNegativeValue(): void
    {
        $fromUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'celsius',
            'temperature',
            1.0,
            1
        );

        $toUnitInfo = $this->createMeasurementDisplayUnitEntity(
            'kelvin',
            'temperature',
            2.0,
            1
        );

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

    private function createMeasurementDisplayUnitEntity(string $shortName, string $type, float $factor, int $precision): MeasurementDisplayUnitEntity
    {
        $entity = new MeasurementDisplayUnitEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->shortName = $shortName;
        $entity->type = $type;
        $entity->factor = $factor;
        $entity->precision = $precision;

        return $entity;
    }
}
