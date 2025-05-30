<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer;

use Shopware\Core\Content\MeasurementSystem\UnitConverter\ConvertedUnit;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class ConvertedUnitSet extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ConvertedUnit::class;
    }

    public function jsonSerialize(): array
    {
        return array_map(
            static fn (ConvertedUnit $unit) => [
                'value' => $unit->value,
                'unit' => $unit->unit,
            ],
            $this->elements
        );
    }

    public function getType(string $name): ?ConvertedUnit
    {
        if (\array_key_exists($name, $this->elements)) {
            return $this->elements[$name];
        }

        return null;
    }
}
