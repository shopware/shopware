<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\UnitProvider;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type MeasurementUnitsType from AbstractMeasurementUnitProvider
 */
#[Package('inventory')]
class MeasurementUnitProvider extends AbstractMeasurementUnitProvider implements ResetInterface
{
    /**
     * @var array<string, MeasurementUnitsType>
     */
    private array $units = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getUnits(): array
    {
        if (!empty($this->units)) {
            return $this->units;
        }

        $query = 'SELECT short_name, type, factor FROM measurement_display_unit';

        /** @var array< string, array{ type: string, factor: string }> $units */
        $units = $this->connection->fetchAllAssociativeIndexed($query);

        return $this->units = array_map(
            static fn (array $unit) => [
                'factor' => (float) $unit['factor'],
                'type' => $unit['type'],
            ],
            $units
        );
    }

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

    public function getDecorated(): AbstractMeasurementUnitProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
