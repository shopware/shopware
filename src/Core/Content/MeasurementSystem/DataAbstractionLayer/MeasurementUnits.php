<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer;

use Shopware\Core\Content\MeasurementSystem\UnitConverter\ConvertedUnit;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class MeasurementUnits extends Struct
{
    /**
     * @param array<string, ConvertedUnit> $valueByUnit
     */
    public function __construct(protected array $valueByUnit = [])
    {
    }

    public function add(string $type, ConvertedUnit $unit): void
    {
        $this->valueByUnit[$type] = $unit;
    }

    public function jsonSerialize(): array
    {
        return array_map(
            static fn (ConvertedUnit $unit) => [
                'value' => $unit->value,
                'unit' => $unit->unit,
            ],
            $this->valueByUnit
        );
    }

    public function getType(string $name): ?ConvertedUnit
    {
        if (\array_key_exists($name, $this->valueByUnit)) {
            return $this->valueByUnit[$name];
        }

        return null;
    }
}
