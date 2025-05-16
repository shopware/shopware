<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\UnitConverter;

use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\UnitProvider\AbstractMeasurementUnitProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type MeasurementUnitsType from AbstractMeasurementUnitProvider
 */
#[Package('inventory')]
class MeasurementUnitConverter extends AbstractMeasurementUnitConverter
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractMeasurementUnitProvider $unitProvider)
    {
    }

    public function convert(float $value, string $fromUnit, string $toUnit, int $decimals = 3): ConvertedUnit
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

    public function getDecorated(): AbstractMeasurementUnitConverter
    {
        throw new DecorationPatternException(self::class);
    }
}
