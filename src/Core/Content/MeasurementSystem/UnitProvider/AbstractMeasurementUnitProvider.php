<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\UnitProvider;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type MeasurementUnitType array{ factor: float, type: string, precision: int}
 */
#[Package('inventory')]
abstract class AbstractMeasurementUnitProvider
{
    abstract public function getDecorated(): AbstractMeasurementUnitProvider;

    /**
     * @return array<string, MeasurementUnitType>
     */
    abstract public function getUnits(): array;

    /**
     * @return MeasurementUnitType
     */
    abstract public function getUnitInfo(string $unit): array;
}
