<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnitSet;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ConvertedUnitSet::class)]
class ConvertedUnitSetTest extends TestCase
{
    private ConvertedUnitSet $unitSet;

    protected function setUp(): void
    {
        $this->unitSet = new ConvertedUnitSet();
    }

    public function testJsonSerializeEmpty(): void
    {
        $result = $this->unitSet->jsonSerialize();

        static::assertSame([], $result);
    }

    public function testGetApiAlias(): void
    {
        $result = $this->unitSet->getApiAlias();

        static::assertSame('converted_unit_set', $result);
    }

    public function testJsonSerializeWithSingleUnit(): void
    {
        $unit = new ConvertedUnit(10.5, 'kg');
        $this->unitSet->addUnit('weight', $unit);

        $result = $this->unitSet->jsonSerialize();

        $expected = [
            'weight' => [
                'value' => 10.5,
                'unit' => 'kg',
            ],
        ];

        static::assertSame($expected, $result);
    }

    public function testJsonSerializeWithMultipleUnits(): void
    {
        $weightUnit = new ConvertedUnit(10.5, 'kg');
        $lengthUnit = new ConvertedUnit(150.0, 'cm');
        $temperatureUnit = new ConvertedUnit(25.5, 'celsius');

        $this->unitSet->addUnit('weight', $weightUnit);
        $this->unitSet->addUnit('length', $lengthUnit);
        $this->unitSet->addUnit('temperature', $temperatureUnit);

        $result = $this->unitSet->jsonSerialize();

        $expected = [
            'weight' => [
                'value' => 10.5,
                'unit' => 'kg',
            ],
            'length' => [
                'value' => 150.0,
                'unit' => 'cm',
            ],
            'temperature' => [
                'value' => 25.5,
                'unit' => 'celsius',
            ],
        ];

        static::assertSame($expected, $result);
    }

    public function testJsonSerializeWithZeroValues(): void
    {
        $unit = new ConvertedUnit(0.0, 'mm');
        $this->unitSet->addUnit('length', $unit);

        $result = $this->unitSet->jsonSerialize();

        $expected = [
            'length' => [
                'value' => 0.0,
                'unit' => 'mm',
            ],
        ];

        static::assertSame($expected, $result);
    }

    public function testJsonSerializeWithNegativeValues(): void
    {
        $unit = new ConvertedUnit(-10.0, 'celsius');
        $this->unitSet->addUnit('temperature', $unit);

        $result = $this->unitSet->jsonSerialize();

        $expected = [
            'temperature' => [
                'value' => -10.0,
                'unit' => 'celsius',
            ],
        ];

        static::assertSame($expected, $result);
    }

    public function testGetTypeExisting(): void
    {
        $unit = new ConvertedUnit(10.5, 'kg');
        $this->unitSet->addUnit('weight', $unit);

        $result = $this->unitSet->getType('weight');

        static::assertSame($unit, $result);
    }

    public function testGetTypeNonExisting(): void
    {
        $result = $this->unitSet->getType('nonexistent');

        static::assertNull($result);
    }

    public function testGetTypeAfterMultipleAdds(): void
    {
        $weightUnit = new ConvertedUnit(10.5, 'kg');
        $lengthUnit = new ConvertedUnit(150.0, 'cm');

        $this->unitSet->addUnit('weight', $weightUnit);
        $this->unitSet->addUnit('length', $lengthUnit);

        static::assertSame($weightUnit, $this->unitSet->getType('weight'));
        static::assertSame($lengthUnit, $this->unitSet->getType('length'));
        static::assertNull($this->unitSet->getType('temperature'));
    }

    public function testGetTypeAfterOverwrite(): void
    {
        $firstUnit = new ConvertedUnit(10.5, 'kg');
        $secondUnit = new ConvertedUnit(15.0, 'pounds');

        $this->unitSet->addUnit('weight', $firstUnit);
        $this->unitSet->addUnit('weight', $secondUnit);

        $result = $this->unitSet->getType('weight');

        static::assertSame($secondUnit, $result);
        static::assertNotSame($firstUnit, $result);
    }

    public function testGetTypeWithEmptyString(): void
    {
        $unit = new ConvertedUnit(5.0, 'mm');
        $this->unitSet->addUnit('', $unit);

        $result = $this->unitSet->getType('');

        static::assertSame($unit, $result);
    }

    public function testGetTypeWithNumericKey(): void
    {
        $unit = new ConvertedUnit(5.0, 'mm');
        $this->unitSet->addUnit('123', $unit);

        $result = $this->unitSet->getType('123');

        static::assertSame($unit, $result);
    }

    public function testComplexScenario(): void
    {
        $weightUnit = new ConvertedUnit(75.5, 'kg');
        $heightUnit = new ConvertedUnit(180.0, 'cm');
        $widthUnit = new ConvertedUnit(60.0, 'cm');
        $lengthUnit = new ConvertedUnit(120.0, 'cm');

        $this->unitSet->addUnit('weight', $weightUnit);
        $this->unitSet->addUnit('height', $heightUnit);
        $this->unitSet->addUnit('width', $widthUnit);
        $this->unitSet->addUnit('length', $lengthUnit);

        $json = $this->unitSet->jsonSerialize();
        static::assertCount(4, $json);
        static::assertArrayHasKey('weight', $json);
        static::assertArrayHasKey('height', $json);
        static::assertArrayHasKey('width', $json);
        static::assertArrayHasKey('length', $json);

        static::assertSame($weightUnit, $this->unitSet->getType('weight'));
        static::assertSame($heightUnit, $this->unitSet->getType('height'));
        static::assertSame($widthUnit, $this->unitSet->getType('width'));
        static::assertSame($lengthUnit, $this->unitSet->getType('length'));

        static::assertNull($this->unitSet->getType('volume'));
    }

    public function testGetUnits(): void
    {
        $unit1 = new ConvertedUnit(10.5, 'kg');
        $unit2 = new ConvertedUnit(150.0, 'cm');

        $this->unitSet->addUnit('weight', $unit1);
        $this->unitSet->addUnit('length', $unit2);

        $result = $this->unitSet->getUnits();

        static::assertCount(2, $result);
        static::assertArrayHasKey('weight', $result);
        static::assertArrayHasKey('length', $result);
        static::assertSame($unit1, $result['weight']);
        static::assertSame($unit2, $result['length']);
    }
}
