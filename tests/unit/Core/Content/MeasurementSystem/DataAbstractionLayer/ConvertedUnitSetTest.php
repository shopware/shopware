<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\ConvertedUnitSet;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\ConvertedUnit;
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

    public function testGetExpectedClass(): void
    {
        // Using reflection to test the protected method
        $reflection = new \ReflectionClass(ConvertedUnitSet::class);
        $method = $reflection->getMethod('getExpectedClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->unitSet);

        static::assertSame(ConvertedUnit::class, $result);
    }

    public function testJsonSerializeEmpty(): void
    {
        $result = $this->unitSet->jsonSerialize();

        static::assertSame([], $result);
    }

    public function testJsonSerializeWithSingleUnit(): void
    {
        $unit = new ConvertedUnit(10.5, 'kg');
        $this->unitSet->set('weight', $unit);

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

        $this->unitSet->set('weight', $weightUnit);
        $this->unitSet->set('length', $lengthUnit);
        $this->unitSet->set('temperature', $temperatureUnit);

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
        $this->unitSet->set('length', $unit);

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
        $this->unitSet->set('temperature', $unit);

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
        $this->unitSet->set('weight', $unit);

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

        $this->unitSet->set('weight', $weightUnit);
        $this->unitSet->set('length', $lengthUnit);

        static::assertSame($weightUnit, $this->unitSet->getType('weight'));
        static::assertSame($lengthUnit, $this->unitSet->getType('length'));
        static::assertNull($this->unitSet->getType('temperature'));
    }

    public function testGetTypeAfterOverwrite(): void
    {
        $firstUnit = new ConvertedUnit(10.5, 'kg');
        $secondUnit = new ConvertedUnit(15.0, 'pounds');

        $this->unitSet->set('weight', $firstUnit);
        $this->unitSet->set('weight', $secondUnit);

        $result = $this->unitSet->getType('weight');

        static::assertSame($secondUnit, $result);
        static::assertNotSame($firstUnit, $result);
    }

    public function testGetTypeWithEmptyString(): void
    {
        $unit = new ConvertedUnit(5.0, 'mm');
        $this->unitSet->set('', $unit);

        $result = $this->unitSet->getType('');

        static::assertSame($unit, $result);
    }

    public function testGetTypeWithNumericKey(): void
    {
        $unit = new ConvertedUnit(5.0, 'mm');
        $this->unitSet->set('123', $unit);

        $result = $this->unitSet->getType('123');

        static::assertSame($unit, $result);
    }

    public function testComplexScenario(): void
    {
        // Test a complex scenario with multiple operations
        $weightUnit = new ConvertedUnit(75.5, 'kg');
        $heightUnit = new ConvertedUnit(180.0, 'cm');
        $widthUnit = new ConvertedUnit(60.0, 'cm');
        $lengthUnit = new ConvertedUnit(120.0, 'cm');

        // Add units
        $this->unitSet->set('weight', $weightUnit);
        $this->unitSet->set('height', $heightUnit);
        $this->unitSet->set('width', $widthUnit);
        $this->unitSet->set('length', $lengthUnit);

        // Test JSON serialization
        $json = $this->unitSet->jsonSerialize();
        static::assertCount(4, $json);
        static::assertArrayHasKey('weight', $json);
        static::assertArrayHasKey('height', $json);
        static::assertArrayHasKey('width', $json);
        static::assertArrayHasKey('length', $json);

        // Test individual retrievals
        static::assertSame($weightUnit, $this->unitSet->getType('weight'));
        static::assertSame($heightUnit, $this->unitSet->getType('height'));
        static::assertSame($widthUnit, $this->unitSet->getType('width'));
        static::assertSame($lengthUnit, $this->unitSet->getType('length'));

        // Test non-existent type
        static::assertNull($this->unitSet->getType('volume'));
    }
} 