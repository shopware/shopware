<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\TwigExtension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitProvider;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MeasurementConvertUnitTwigFilter::class)]
class MeasurementConvertUnitTwigFilterTest extends TestCase
{
    private AbstractMeasurementUnitProvider&Stub $unitProvider;

    private AbstractMeasurementUnitConverter&Stub $unitConverter;

    private MeasurementConvertUnitTwigFilter $filter;

    protected function setUp(): void
    {
        $this->unitProvider = static::createStub(AbstractMeasurementUnitProvider::class);
        $this->unitConverter = static::createStub(AbstractMeasurementUnitConverter::class);
        $this->filter = new MeasurementConvertUnitTwigFilter($this->unitProvider, $this->unitConverter);
    }

    public function testGetFilters(): void
    {
        $filters = $this->filter->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(TwigFilter::class, $filters[0]);
        static::assertSame('sw_convert_unit', $filters[0]->getName());
    }

    public function testConvertWithNonNumericValue(): void
    {
        $twigContext = [];
        $value = 'not_numeric';

        $result = $this->filter->convert($twigContext, $value);

        static::assertSame('not_numeric', $result);
    }

    public function testConvertWithNullValue(): void
    {
        $twigContext = [];
        $value = null;

        $result = $this->filter->convert($twigContext, $value);

        static::assertNull($result);
    }

    public function testConvertWithAutoDetectedUnit(): void
    {
        $context = Generator::generateSalesChannelContext();

        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'kg',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $twigContext = ['context' => $context];

        $measurementUnit = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            10.0,
            2
        );

        $unitProvider = $this->createMock(AbstractMeasurementUnitProvider::class);
        $unitProvider
            ->expects($this->once())
            ->method('getUnitInfo')
            ->with('mm')
            ->willReturn($measurementUnit);
        $this->unitProvider = $unitProvider;

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, '100', 'mm');

        static::assertSame('10 cm', $result);
    }

    public function testConvertWithAutoDetectedUnitFromSalesChannelContextFallback(): void
    {
        $context = Generator::generateSalesChannelContext();

        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'kg',
            ]
        );

        $context->setMeasurementSystem($measurementUnits);

        $twigContext = ['salesChannelContext' => $context];

        $measurementUnit = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            10.0,
            2
        );

        $unitProvider = $this->createMock(AbstractMeasurementUnitProvider::class);
        $unitProvider
            ->expects($this->once())
            ->method('getUnitInfo')
            ->with('mm')
            ->willReturn($measurementUnit);
        $this->unitProvider = $unitProvider;

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, '100', 'mm');

        static::assertSame('10 cm', $result);
    }

    public function testConvertWithExplicitToUnit(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(1000.0, 'mm', 'm', null)
            ->willReturn(new ConvertedUnit(1.0, 'm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 1000, 'mm', 'm');

        static::assertSame('1 m', $result);
    }

    public function testConvertWithCustomPrecision(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(1000.0, 'mm', 'm', 3)
            ->willReturn(new ConvertedUnit(1.000, 'm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 1000, 'mm', 'm', 3);

        static::assertSame('1 m', $result);
    }

    public function testConvertWithNoToUnitAndNoContext(): void
    {
        $twigContext = [];

        $result = $this->filter->convert($twigContext, 100, 'mm', null);

        static::assertSame('100 mm', $result);
    }

    public function testConvertWithStringValue(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, '100', 'mm', 'cm');

        static::assertSame('10 cm', $result);
    }

    public function testConvertWithFloatValue(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.5, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.05, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 100.5, 'mm', 'cm');

        static::assertSame('10.05 cm', $result);
    }

    public function testConvertWithZeroValue(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(0.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(0.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 0, 'mm', 'cm');

        static::assertSame('0 cm', $result);
    }

    public function testConvertWithNegativeValue(): void
    {
        $twigContext = [];

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(-10.0, 'celsius', 'kelvin', null)
            ->willReturn(new ConvertedUnit(263.15, 'kelvin'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, -10, 'celsius', 'kelvin');

        static::assertSame('263.15 kelvin', $result);
    }

    public function testConvertWithContextButNoMeasurementSystem(): void
    {
        $context = Generator::generateSalesChannelContext();
        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'kg',
            ]
        );

        $twigContext = ['context' => $context];

        $measurementUnit = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            10.0,
            2
        );

        $unitProvider = $this->createMock(AbstractMeasurementUnitProvider::class);
        $unitProvider
            ->expects($this->once())
            ->method('getUnitInfo')
            ->with('mm')
            ->willReturn($measurementUnit);
        $this->unitProvider = $unitProvider;

        $context->setMeasurementSystem($measurementUnits);

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 100, 'mm', null);

        static::assertSame('10 cm', $result);
    }

    public function testConvertWithDefaultFromUnit(): void
    {
        $context = Generator::generateSalesChannelContext();
        $measurementUnits = new MeasurementUnits(
            'metric',
            [
                'length' => 'cm',
                'weight' => 'kg',
            ]
        );
        $context->setMeasurementSystem($measurementUnits);

        $twigContext = ['context' => $context];

        $measurementUnit = $this->createMeasurementDisplayUnitEntity(
            'mm',
            'length',
            10.0,
            2
        );

        $unitProvider = $this->createMock(AbstractMeasurementUnitProvider::class);
        $unitProvider
            ->expects($this->once())
            ->method('getUnitInfo')
            ->with('mm')
            ->willReturn($measurementUnit);
        $this->unitProvider = $unitProvider;

        $unitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $unitConverter
            ->expects($this->once())
            ->method('convert')
            ->with(100.0, 'mm', 'cm', null)
            ->willReturn(new ConvertedUnit(10.0, 'cm'));
        $this->unitConverter = $unitConverter;

        $result = $this->createFilter()->convert($twigContext, 100);

        static::assertSame('10 cm', $result);
    }

    private function createFilter(): MeasurementConvertUnitTwigFilter
    {
        return new MeasurementConvertUnitTwigFilter($this->unitProvider, $this->unitConverter);
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
