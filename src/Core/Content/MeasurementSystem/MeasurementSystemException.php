<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class MeasurementSystemException extends HttpException
{
    public const UNSUPPORTED_MEASUREMENT_SYSTEM = 'CONTENT_UNSUPPORTED_MEASUREMENT_SYSTEM_TYPE';

    public const UNSUPPORTED_MEASUREMENT_UNIT = 'CONTENT_UNSUPPORTED_MEASUREMENT_SYSTEM_UNIT';

    public const INCOMPATIBLE_MEASUREMENT_UNITS = 'CONTENT_INCOMPATIBLE_MEASUREMENT_UNITS';

    public const MEASUREMENT_UNIT_CANT_HAVE_ZERO_FACTOR = 'CONTENT_MEASUREMENT_UNIT_CANT_HAVE_ZERO_FACTOR';

    /**
     * @param array<string> $possibleTypes
     */
    public static function unsupportedMeasurementType(string $type, array $possibleTypes): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_MEASUREMENT_SYSTEM,
            'The measurement system type "{{ type }}" is not supported. Possible types are: {{ possibleTypes }}',
            [
                'type' => $type,
                'possibleTypes' => implode(', ', $possibleTypes),
            ],
        );
    }

    /**
     * @param array<string> $possibleUnits
     */
    public static function unsupportedMeasurementUnit(string $unit, array $possibleUnits): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_MEASUREMENT_UNIT,
            'The measurement system unit "{{ unit }}" is not supported. Possible units are: {{ possibleUnits }}',
            [
                'unit' => $unit,
                'possibleUnits' => implode(', ', $possibleUnits),
            ],
        );
    }

    public static function measurementUnitCantHaveZeroFactor(string $unit): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEASUREMENT_UNIT_CANT_HAVE_ZERO_FACTOR,
            'The measurement system unit "{{ unit }}" cannot have a factor of zero.',
            [
                'unit' => $unit,
            ],
        );
    }

    public static function incompatibleMeasurementUnits(string $fromUnit, string $toUnit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INCOMPATIBLE_MEASUREMENT_UNITS,
            'The measurement units "{{ fromUnit }}" and "{{ toUnit }}" are incompatible.',
            [
                'fromUnit' => $fromUnit,
                'toUnit' => $toUnit,
            ],
        );
    }
}
