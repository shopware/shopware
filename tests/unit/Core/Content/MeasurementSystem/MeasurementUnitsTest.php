<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;

/**
 * @internal
 */
#[CoversClass(MeasurementUnits::class)]
class MeasurementUnitsTest extends TestCase
{
    public function testConstructorSetsSystemAndUnits(): void
    {
        $system = 'metric';
        $units = [
            'length' => 'cm',
            'weight' => 'g',
            'volume' => 'ml',
        ];

        $measurementUnits = new MeasurementUnits($system, $units);

        static::assertSame($system, $measurementUnits->getSystem());
        static::assertSame($units, $measurementUnits->getUnits());
    }

    public function testGetUnitReturnsCorrectUnit(): void
    {
        $units = [
            'length' => 'cm',
            'weight' => 'g',
            'volume' => 'ml',
        ];

        $measurementUnits = new MeasurementUnits('metric', $units);

        static::assertSame('cm', $measurementUnits->getUnit('length'));
        static::assertSame('g', $measurementUnits->getUnit('weight'));
        static::assertSame('ml', $measurementUnits->getUnit('volume'));
    }

    public function testGetUnitThrowsExceptionForUnsupportedType(): void
    {
        $units = [
            'length' => 'cm',
            'weight' => 'g',
        ];

        $measurementUnits = new MeasurementUnits('metric', $units);

        $this->expectException(MeasurementSystemException::class);
        $measurementUnits->getUnit('temperature');
    }

    public function testGetUnitsReturnsAllUnits(): void
    {
        $measurementUnits = new MeasurementUnits('custom', []);

        static::assertSame([], $measurementUnits->getUnits());

        $units = [
            'length' => 'inch',
            'weight' => 'pound',
            'volume' => 'gallon',
        ];

        $measurementUnits = new MeasurementUnits('imperial', $units);

        static::assertSame($units, $measurementUnits->getUnits());
    }

    public function testCreateDefaultUnitsReturnsExpectedDefaults(): void
    {
        $defaultUnits = MeasurementUnits::createDefaultUnits();

        static::assertSame(MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM, $defaultUnits->getSystem());
        static::assertSame(MeasurementUnits::DEFAULT_LENGTH_UNIT, $defaultUnits->getUnit('length'));
        static::assertSame(MeasurementUnits::DEFAULT_WEIGHT_UNIT, $defaultUnits->getUnit('weight'));

        $expectedUnits = [
            'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
            'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
        ];
        static::assertSame($expectedUnits, $defaultUnits->getUnits());
    }

    public function testGetSystemReturnsCorrectSystem(): void
    {
        $system1 = 'metric';
        $system2 = 'imperial';
        $system3 = 'custom-system';

        $measurementUnits1 = new MeasurementUnits($system1, []);
        $measurementUnits2 = new MeasurementUnits($system2, []);
        $measurementUnits3 = new MeasurementUnits($system3, []);

        static::assertSame($system1, $measurementUnits1->getSystem());
        static::assertSame($system2, $measurementUnits2->getSystem());
        static::assertSame($system3, $measurementUnits3->getSystem());
    }

    public function testGetApiAliasReturnsCorrectAlias(): void
    {
        $measurementUnits = new MeasurementUnits('metric', []);

        static::assertSame('measurement_system_info', $measurementUnits->getApiAlias());
    }

    public function testMeasurementUnitsWithSpecialCharacters(): void
    {
        $units = [
            'temperature' => '°C',
            'pressure' => 'kg/cm²',
            'angle' => '°',
        ];

        $measurementUnits = new MeasurementUnits('scientific', $units);

        static::assertSame('°C', $measurementUnits->getUnit('temperature'));
        static::assertSame('kg/cm²', $measurementUnits->getUnit('pressure'));
        static::assertSame('°', $measurementUnits->getUnit('angle'));
    }

    public function testJsonSerializeReturnsCorrectArray(): void
    {
        $units = [
            'length' => 'cm',
            'weight' => 'g',
        ];

        $measurementUnits = new MeasurementUnits('metric', $units);

        $expectedArray = [
            'extensions' => [],
            'system' => 'metric',
            'units' => $units,
        ];

        static::assertSame($expectedArray, $measurementUnits->jsonSerialize());
    }

    public function testSetUnit(): void
    {
        $measurementUnits = new MeasurementUnits('metric', [
            'length' => 'm',
        ]);

        static::assertSame('m', $measurementUnits->getUnit('length'));

        // overwrite existing unit
        $measurementUnits->setUnit('length', 'cm');
        static::assertSame('cm', $measurementUnits->getUnit('length'));

        // add new unit
        $measurementUnits->setUnit('volume', 'l');
        static::assertSame('l', $measurementUnits->getUnit('volume'));
    }
}
