<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class MeasurementUnits extends Struct
{
    public const DEFAULT_MEASUREMENT_SYSTEM = 'metric';

    public const DEFAULT_LENGTH_UNIT = 'mm';

    public const DEFAULT_WEIGHT_UNIT = 'kg';

    /**
     * @param array<string, string> $units
     */
    public function __construct(protected string $system, protected array $units)
    {
    }

    public function getUnit(string $type): string
    {
        if (!\array_key_exists($type, $this->units)) {
            throw MeasurementSystemException::unsupportedMeasurementType($type, array_keys($this->units));
        }

        return $this->units[$type];
    }

    public function setUnit(string $type, string $unit): void
    {
        $this->units[$type] = $unit;
    }

    /**
     * @return array<string, string>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public static function createDefaultUnits(): self
    {
        return new self(
            self::DEFAULT_MEASUREMENT_SYSTEM,
            [
                MeasurementUnitTypeEnum::LENGTH->value => self::DEFAULT_LENGTH_UNIT,
                MeasurementUnitTypeEnum::WEIGHT->value => self::DEFAULT_WEIGHT_UNIT,
            ]
        );
    }

    public function getSystem(): string
    {
        return $this->system;
    }

    public function getApiAlias(): string
    {
        return 'measurement_system_info';
    }
}
