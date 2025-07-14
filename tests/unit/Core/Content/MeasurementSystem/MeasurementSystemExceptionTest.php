<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MeasurementSystemException::class)]
class MeasurementSystemExceptionTest extends TestCase
{
    public function testUnsupportedMeasurementType(): void
    {
        $type = 'invalid_type';
        $possibleTypes = ['length', 'weight', 'temperature'];

        $exception = MeasurementSystemException::unsupportedMeasurementType($type, $possibleTypes);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_SYSTEM, $exception->getErrorCode());
        static::assertSame('The measurement system type "invalid_type" is not supported. Possible types are: length, weight, temperature', $exception->getMessage());
        static::assertSame([
            'type' => $type,
            'possibleTypes' => 'length, weight, temperature',
        ], $exception->getParameters());
    }

    public function testUnsupportedMeasurementTypeWithEmptyPossibleTypes(): void
    {
        $type = 'unknown';
        $possibleTypes = [];

        $exception = MeasurementSystemException::unsupportedMeasurementType($type, $possibleTypes);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_SYSTEM, $exception->getErrorCode());
        static::assertSame('The measurement system type "unknown" is not supported. Possible types are: ', $exception->getMessage());
        static::assertSame([
            'type' => $type,
            'possibleTypes' => '',
        ], $exception->getParameters());
    }

    public function testUnsupportedMeasurementTypeWithSinglePossibleType(): void
    {
        $type = 'volume';
        $possibleTypes = ['length'];

        $exception = MeasurementSystemException::unsupportedMeasurementType($type, $possibleTypes);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_SYSTEM, $exception->getErrorCode());
        static::assertSame('The measurement system type "volume" is not supported. Possible types are: length', $exception->getMessage());
        static::assertSame([
            'type' => $type,
            'possibleTypes' => 'length',
        ], $exception->getParameters());
    }

    public function testUnsupportedMeasurementUnit(): void
    {
        $unit = 'invalid_unit';
        $possibleUnits = ['mm', 'cm', 'm', 'km'];

        $exception = MeasurementSystemException::unsupportedMeasurementUnit($unit, $possibleUnits);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_UNIT, $exception->getErrorCode());
        static::assertSame('The measurement system unit "invalid_unit" is not supported. Possible units are: mm, cm, m, km', $exception->getMessage());
        static::assertSame([
            'unit' => $unit,
            'possibleUnits' => 'mm, cm, m, km',
        ], $exception->getParameters());
    }

    public function testUnsupportedMeasurementUnitWithEmptyPossibleUnits(): void
    {
        $unit = 'unknown';
        $possibleUnits = [];

        $exception = MeasurementSystemException::unsupportedMeasurementUnit($unit, $possibleUnits);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_UNIT, $exception->getErrorCode());
        static::assertSame('The measurement system unit "unknown" is not supported. Possible units are: ', $exception->getMessage());
        static::assertSame([
            'unit' => $unit,
            'possibleUnits' => '',
        ], $exception->getParameters());
    }

    public function testUnsupportedMeasurementUnitWithSinglePossibleUnit(): void
    {
        $unit = 'yards';
        $possibleUnits = ['mm'];

        $exception = MeasurementSystemException::unsupportedMeasurementUnit($unit, $possibleUnits);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::UNSUPPORTED_MEASUREMENT_UNIT, $exception->getErrorCode());
        static::assertSame('The measurement system unit "yards" is not supported. Possible units are: mm', $exception->getMessage());
        static::assertSame([
            'unit' => $unit,
            'possibleUnits' => 'mm',
        ], $exception->getParameters());
    }

    public function testIncompatibleMeasurementUnits(): void
    {
        $fromUnit = 'mm';
        $toUnit = 'kg';

        $exception = MeasurementSystemException::incompatibleMeasurementUnits($fromUnit, $toUnit);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::INCOMPATIBLE_MEASUREMENT_UNITS, $exception->getErrorCode());
        static::assertSame('The measurement units "mm" and "kg" are incompatible.', $exception->getMessage());
        static::assertSame([
            'fromUnit' => $fromUnit,
            'toUnit' => $toUnit,
        ], $exception->getParameters());
    }

    public function testIncompatibleMeasurementUnitsWithSameType(): void
    {
        $fromUnit = 'celsius';
        $toUnit = 'fahrenheit';

        $exception = MeasurementSystemException::incompatibleMeasurementUnits($fromUnit, $toUnit);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::INCOMPATIBLE_MEASUREMENT_UNITS, $exception->getErrorCode());
        static::assertSame('The measurement units "celsius" and "fahrenheit" are incompatible.', $exception->getMessage());
        static::assertSame([
            'fromUnit' => $fromUnit,
            'toUnit' => $toUnit,
        ], $exception->getParameters());
    }

    public function testIncompatibleMeasurementUnitsWithEmptyUnits(): void
    {
        $fromUnit = '';
        $toUnit = '';

        $exception = MeasurementSystemException::incompatibleMeasurementUnits($fromUnit, $toUnit);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::INCOMPATIBLE_MEASUREMENT_UNITS, $exception->getErrorCode());
        static::assertSame('The measurement units "" and "" are incompatible.', $exception->getMessage());
        static::assertSame([
            'fromUnit' => $fromUnit,
            'toUnit' => $toUnit,
        ], $exception->getParameters());
    }

    public function testMeasurementUnitCantHaveZeroFactor(): void
    {
        $unit = 'invalid_unit';

        $exception = MeasurementSystemException::measurementUnitCantHaveZeroFactor($unit);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MeasurementSystemException::MEASUREMENT_UNIT_CANT_HAVE_ZERO_FACTOR, $exception->getErrorCode());
        static::assertSame('The measurement system unit "invalid_unit" cannot have a factor of zero.', $exception->getMessage());
        static::assertSame(['unit' => $unit], $exception->getParameters());
    }
}
