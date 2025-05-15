<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type MeasurementUnitsType array<string, array{ factor: string, type: string}>
 */
#[Package('inventory')]
interface MeasurementUnitProviderInterface
{
    /**
     * @return array<string, MeasurementUnitsType>
     */
    public function getUnits(): array;

    /**
     * @return array{ factor: string, type: string}
     */
    public function getUnitInfo(string $unit): array;
}
