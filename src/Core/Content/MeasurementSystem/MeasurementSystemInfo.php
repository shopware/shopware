<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class MeasurementSystemInfo extends Struct
{
    public const DEFAULT_LENGTH_UNIT = 'mm';

    public const DEFAULT_WEIGHT_UNIT = 'kg';

    /**
     * @param array<string, string> $units
     */
    public function __construct(protected array $units)
    {
    }

    public function addUnit(string $type, string $unit): void
    {
        $this->units[$type] = $unit;
    }

    public function getUnit(string $type): string
    {
        if (!\array_key_exists($type, $this->units)) {
            throw MeasurementSystemException::unsupportedMeasurementType($type, array_keys($this->units));
        }

        return $this->units[$type];
    }
}
