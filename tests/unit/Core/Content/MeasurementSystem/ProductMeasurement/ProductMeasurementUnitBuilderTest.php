<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\ProductMeasurement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\ConvertedUnitSet;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\ConvertedUnit;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductMeasurementUnitBuilder::class)]
class ProductMeasurementUnitBuilderTest extends TestCase
{
    private AbstractMeasurementUnitConverter $unitConverter;
    private ProductMeasurementUnitBuilder $builder;

    protected function setUp(): void
    {
        $this->unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $this->builder = new ProductMeasurementUnitBuilder($this->unitConverter);
    }

    public function testBuildWithAllMeasurements(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);
        $measurementUnits = $this->createMock(MeasurementUnits::class);

        // Setup measurement units mock
        $measurementUnits
            ->expects(static::exactly(2))
            ->method('getUnit')
            ->willReturnMap([
                ['length', 'cm'],
                ['weight', 'g'],
            ]);

        $context
            ->expects(static::exactly(2))
            ->method('getMeasurementSystem')
            ->willReturn($measurementUnits);

        // Setup product mock - each field called once now (cached)
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                100.0,         // width called once - float
                200.0,         // height called once - float  
                300.0,         // length called once - float
                1.5            // weight called once - float
            );

        // Setup unit converter expectations
        $this->unitConverter
            ->expects(static::exactly(4))
            ->method('convert')
            ->willReturnMap([
                [100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(10.0, 'cm')],
                [200.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(20.0, 'cm')],
                [300.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(30.0, 'cm')],
                [1.5, MeasurementUnits::DEFAULT_WEIGHT_UNIT, 'g', null, new ConvertedUnit(1500.0, 'g')],
            ]);

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // Verify all measurements are converted
        static::assertNotNull($result->getType('width'));
        static::assertNotNull($result->getType('height'));
        static::assertNotNull($result->getType('length'));
        static::assertNotNull($result->getType('weight'));

        static::assertSame(10.0, $result->getType('width')->value);
        static::assertSame('cm', $result->getType('width')->unit);
        
        static::assertSame(20.0, $result->getType('height')->value);
        static::assertSame('cm', $result->getType('height')->unit);
        
        static::assertSame(30.0, $result->getType('length')->value);
        static::assertSame('cm', $result->getType('length')->unit);
        
        static::assertSame(1500.0, $result->getType('weight')->value);
        static::assertSame('g', $result->getType('weight')->unit);
    }

    public function testBuildWithPartialMeasurements(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);
        $measurementUnits = $this->createMock(MeasurementUnits::class);

        // Setup measurement units mock
        $measurementUnits
            ->expects(static::exactly(2))
            ->method('getUnit')
            ->willReturnMap([
                ['length', 'cm'],
                ['weight', 'g'],
            ]);

        $context
            ->expects(static::exactly(2))
            ->method('getMeasurementSystem')
            ->willReturn($measurementUnits);

        // Setup product mock - only width is float, each field called once now
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                100.0,         // width called once - float
                null,          // height called once - null
                'invalid',     // length called once - invalid
                null           // weight called once - null
            );

        // Only width should be converted
        $this->unitConverter
            ->expects(static::once())
            ->method('convert')
            ->with(100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // Only width should be set
        static::assertNotNull($result->getType('width'));
        static::assertNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }

    public function testBuildWithNoMeasurements(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);

        // Setup product mock - no float measurements, each field called once
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                null,          // width called once
                null,          // height called once
                null,          // length called once
                null           // weight called once
            );

        // No conversions should happen
        $this->unitConverter
            ->expects(static::never())
            ->method('convert');

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // All measurements should be null
        static::assertNull($result->getType('width'));
        static::assertNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }

    public function testBuildWithZeroMeasurements(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);
        $measurementUnits = $this->createMock(MeasurementUnits::class);

        // Setup measurement units mock
        $measurementUnits
            ->expects(static::exactly(2))
            ->method('getUnit')
            ->willReturnMap([
                ['length', 'cm'],
                ['weight', 'g'],
            ]);

        $context
            ->expects(static::exactly(2))
            ->method('getMeasurementSystem')
            ->willReturn($measurementUnits);

        // Setup product mock: width, height, length, weight = 4 calls (cached now)
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                0.0,           // width called once - zero (float)
                0.0,           // height called once - zero (float)
                null,          // length called once - null
                0.0            // weight called once - zero (float)
            );

        // Zero values should still be converted
        $this->unitConverter
            ->expects(static::exactly(3))
            ->method('convert')
            ->willReturnMap([
                [0.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(0.0, 'cm')],
                [0.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(0.0, 'cm')],
                [0.0, MeasurementUnits::DEFAULT_WEIGHT_UNIT, 'g', null, new ConvertedUnit(0.0, 'g')],
            ]);

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // Width, height, and weight should be converted, length should be null
        static::assertNotNull($result->getType('width'));
        static::assertNotNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNotNull($result->getType('weight'));

        static::assertSame(0.0, $result->getType('width')->value);
        static::assertSame(0.0, $result->getType('height')->value);
        static::assertSame(0.0, $result->getType('weight')->value);
    }

    public function testBuildWithOnlyLengthMeasurements(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);
        $measurementUnits = $this->createMock(MeasurementUnits::class);

        // Setup measurement units mock
        $measurementUnits
            ->expects(static::exactly(2))
            ->method('getUnit')
            ->willReturnMap([
                ['length', 'm'],
                ['weight', 'g'],
            ]);

        $context
            ->expects(static::exactly(2))
            ->method('getMeasurementSystem')
            ->willReturn($measurementUnits);

        // Setup product mock: width, height, length, weight = 4 calls (cached)
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                50.0,          // width called once - float
                100.0,         // height called once - float
                150.0,         // length called once - float
                null           // weight called once - null
            );

        // Only length measurements should be converted
        $this->unitConverter
            ->expects(static::exactly(3))
            ->method('convert')
            ->willReturnMap([
                [50.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.05, 'm')],
                [100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.1, 'm')],
                [150.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.15, 'm')],
            ]);

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // Length measurements should be converted, weight should be null
        static::assertNotNull($result->getType('width'));
        static::assertNotNull($result->getType('height'));
        static::assertNotNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }

    public function testBuildWithOnlyWeightMeasurement(): void
    {
        $product = $this->createMock(Entity::class);
        $context = $this->createMock(SalesChannelContext::class);
        $measurementUnits = $this->createMock(MeasurementUnits::class);

        // Setup measurement units mock
        $measurementUnits
            ->expects(static::exactly(2))
            ->method('getUnit')
            ->willReturnMap([
                ['length', 'cm'],
                ['weight', 'lb'],
            ]);

        $context
            ->expects(static::exactly(2))
            ->method('getMeasurementSystem')
            ->willReturn($measurementUnits);

        // Setup product mock: width, height, length, weight = 4 calls (cached)
        $product
            ->expects(static::exactly(4))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                null,          // width called once - null
                null,          // height called once - null
                null,          // length called once - null
                2.5            // weight called once - float
            );

        // Only weight should be converted
        $this->unitConverter
            ->expects(static::once())
            ->method('convert')
            ->with(2.5, MeasurementUnits::DEFAULT_WEIGHT_UNIT, 'lb', null)
            ->willReturn(new ConvertedUnit(5.5, 'lb'));

        $result = $this->builder->build($product, $context);

        static::assertInstanceOf(ConvertedUnitSet::class, $result);
        
        // Only weight should be converted
        static::assertNull($result->getType('width'));
        static::assertNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNotNull($result->getType('weight'));

        static::assertSame(5.5, $result->getType('weight')->value);
        static::assertSame('lb', $result->getType('weight')->unit);
    }
} 