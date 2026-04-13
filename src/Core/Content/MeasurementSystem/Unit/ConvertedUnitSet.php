<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Unit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class ConvertedUnitSet extends Struct
{
    /**
     * @param array<string, ConvertedUnit> $units
     */
    public function __construct(private array $units = [])
    {
    }

    public function addUnit(string $name, ConvertedUnit $unit): void
    {
        $this->units[$name] = $unit;
    }

    /**
     * @return array<string, array{ value: float, unit: string}>
     */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (ConvertedUnit $unit) => [
                'value' => $unit->value,
                'unit' => $unit->unit,
            ],
            $this->units
        );
    }

    public function getType(string $name): ?ConvertedUnit
    {
        return $this->units[$name] ?? null;
    }

    /**
     * @return array<string, ConvertedUnit>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public function getApiAlias(): string
    {
        return 'converted_unit_set';
    }
}
