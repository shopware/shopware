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

    public static function incompatibleMeasurementUnits(string $fromUnit, string $toUnit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            'CONTENT_INCOMPATIBLE_MEASUREMENT_UNITS',
            'The measurement units "{{ fromUnit }}" and "{{ toUnit }}" are incompatible.',
            [
                'fromUnit' => $fromUnit,
                'toUnit' => $toUnit,
            ],
        );
    }
}
