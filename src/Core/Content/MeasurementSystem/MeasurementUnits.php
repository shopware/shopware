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
    public function __construct(protected string $name, protected array $units)
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

    /**
     * @return array<string, string>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function createDefaultUnits(): self
    {
        return new self(
            self::DEFAULT_MEASUREMENT_SYSTEM,
            [
                'length' => self::DEFAULT_LENGTH_UNIT,
                'weight' => self::DEFAULT_WEIGHT_UNIT,
            ]
        );
    }

    public function getApiAlias(): string
    {
        return 'measurement_system_info';
    }
}
