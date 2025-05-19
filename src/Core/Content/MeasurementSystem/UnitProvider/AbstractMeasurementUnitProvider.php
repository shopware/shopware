<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\UnitProvider;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type MeasurementUnitsType array{ factor: float, type: string}
 */
#[Package('inventory')]
abstract class AbstractMeasurementUnitProvider
{
    abstract public function getDecorated(): AbstractMeasurementUnitProvider;

    /**
     * @return array<string, MeasurementUnitsType>
     */
    abstract public function getUnits(): array;

    /**
     * @return MeasurementUnitsType
     */
    abstract public function getUnitInfo(string $unit): array;
}
