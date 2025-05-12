<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Service;

use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type MeasurementUnitsType from MeasurementUnitProviderInterface
 */
#[Package('inventory')]
class MeasurementUnitConverter implements MeasurementUnitConverterInterface
{
    public function __construct(private readonly MeasurementUnitProvider $unitProvider)
    {
    }

    public function convert(float $value, string $fromUnit, string $toUnit, int $decimals = 2): ConvertedUnit
    {
        if ($fromUnit === $toUnit) {
            return new ConvertedUnit($value, $toUnit);
        }

        $fromUnitInfo = $this->unitProvider->getUnitInfo($fromUnit);
        $toUnitInfo = $this->unitProvider->getUnitInfo($toUnit);

        if ($fromUnitInfo['type'] !== $toUnitInfo['type']) {
            throw MeasurementSystemException::incompatibleMeasurementUnits($fromUnit, $toUnit);
        }

        $value = $value * $fromUnitInfo['factor'] / $toUnitInfo['factor'];

        $roundedValue = round($value, $decimals);

        return new ConvertedUnit($roundedValue, $toUnit);
    }
}
