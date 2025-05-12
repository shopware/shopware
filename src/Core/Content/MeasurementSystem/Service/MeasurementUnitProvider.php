<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type MeasurementUnitsType from MeasurementUnitProviderInterface
 */
#[Package('inventory')]
class MeasurementUnitProvider implements MeasurementUnitProviderInterface, ResetInterface
{
    /**
     * @var MeasurementUnitsType
     */
    private readonly array $units;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, MeasurementUnitsType>
     */
    public function getUnits(): array
    {
        if (!empty($this->units)) {
            return $this->units;
        }

        $query = 'SELECT short_name, type, factor FROM measurement_display_unit';

        $units = $this->connection->fetchAllAssociativeIndexed($query);

        return $this->units = array_map(
            static fn (array $unit) => [
                'factor' => (float) $unit['factor'],
                'type' => $unit['type'],
            ],
            $units
        );
    }

    /**
     * @return MeasurementUnitsType
     */
    public function getUnitInfo(string $unit): array
    {
        $units = $this->getUnits();

        if (!\array_key_exists($unit, $units)) {
            throw MeasurementSystemException::unsupportedMeasurementUnit($unit, array_keys($units));
        }

        return $this->units[$unit];
    }

    public function reset(): void
    {
        $this->units = [];
    }
}
