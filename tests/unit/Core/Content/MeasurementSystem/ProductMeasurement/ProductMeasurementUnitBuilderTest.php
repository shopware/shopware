<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\ProductMeasurement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductMeasurementUnitBuilder::class)]
class ProductMeasurementUnitBuilderTest extends TestCase
{
    private AbstractMeasurementUnitConverter&MockObject $unitConverter;

    private ProductMeasurementUnitBuilder $builder;

    protected function setUp(): void
    {
        $this->unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $this->builder = new ProductMeasurementUnitBuilder($this->unitConverter);
    }

    public function testBuildWithAllMeasurements(): void
    {
        $product = new ProductEntity();
        $product->setId('product-id');
        $product->setWidth(100.0);
        $product->setHeight(200.0);
        $product->setLength(300.0);
        $product->setWeight(1.5);

        $context = Generator::generateSalesChannelContext();
        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'g',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $this->unitConverter
            ->expects($this->exactly(4))
            ->method('convert')
            ->willReturnMap([
                [100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(10.0, 'cm')],
                [200.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(20.0, 'cm')],
                [300.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(30.0, 'cm')],
                [1.5, MeasurementUnits::DEFAULT_WEIGHT_UNIT, 'g', null, new ConvertedUnit(1500.0, 'g')],
            ]);

        $result = $this->builder->buildFromContext($product, $context);

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
        $product = new PartialEntity();

        $product->assign([
            'width' => 100.0,
            'height' => null,
            'length' => 'invalid',
            'weight' => null,
        ]);

        $context = Generator::generateSalesChannelContext();

        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'g',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $this->unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));

        $result = $this->builder->buildFromContext($product, $context);

        static::assertNotNull($result->getType('width'));
        static::assertNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }

    public function testBuildWithNoMeasurements(): void
    {
        $product = new PartialEntity();

        $product->assign([
            'width' => null,
            'height' => null,
            'length' => null,
            'weight' => null,
        ]);

        $context = Generator::generateSalesChannelContext();

        $this->unitConverter
            ->expects($this->never())
            ->method('convert');

        $result = $this->builder->buildFromContext($product, $context);

        static::assertNull($result->getType('width'));
        static::assertNull($result->getType('height'));
        static::assertNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }

    public function testBuildWithZeroMeasurements(): void
    {
        $product = new PartialEntity();

        $product->assign([
            'width' => 0.0,
            'height' => 0.0,
            'length' => null,
            'weight' => 0.0,
        ]);

        $context = Generator::generateSalesChannelContext();
        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'g',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $this->unitConverter
            ->expects($this->exactly(3))
            ->method('convert')
            ->willReturnMap([
                [0.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(0.0, 'cm')],
                [0.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'cm', null, new ConvertedUnit(0.0, 'cm')],
                [0.0, MeasurementUnits::DEFAULT_WEIGHT_UNIT, 'g', null, new ConvertedUnit(0.0, 'g')],
            ]);

        $result = $this->builder->buildFromContext($product, $context);

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
        $product = new PartialEntity();
        $product->assign([
            'width' => 50.0,
            'height' => 100.0,
            'length' => 150.0,
            'weight' => null,
        ]);
        $context = Generator::generateSalesChannelContext();

        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'm',
                'weight' => 'g',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $this->unitConverter
            ->expects($this->exactly(3))
            ->method('convert')
            ->willReturnMap([
                [50.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.05, 'm')],
                [100.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.1, 'm')],
                [150.0, MeasurementUnits::DEFAULT_LENGTH_UNIT, 'm', null, new ConvertedUnit(0.15, 'm')],
            ]);

        $result = $this->builder->buildFromContext($product, $context);

        static::assertNotNull($result->getType('width'));
        static::assertNotNull($result->getType('height'));
        static::assertNotNull($result->getType('length'));
        static::assertNull($result->getType('weight'));
    }
}
