<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Unit;

use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\Service\ResetInterface;

#[Package('inventory')]
class MeasurementUnitProvider extends AbstractMeasurementUnitProvider implements ResetInterface
{
    /**
     * @var EntityCollection<MeasurementDisplayUnitEntity>|null
     */
    private ?EntityCollection $units = null;

    /**
     * @param EntityRepository<EntityCollection<MeasurementDisplayUnitEntity>> $measurementDisplayUnitRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $measurementDisplayUnitRepository)
    {
    }

    public function getUnitInfo(string $unit): MeasurementDisplayUnitEntity
    {
        $units = $this->getUnits();

        $availableUnits = $units->map(static function (MeasurementDisplayUnitEntity $unit) {
            return $unit->shortName;
        });

        $foundUnit = $units->firstWhere(static function (MeasurementDisplayUnitEntity $unitEntity) use ($unit) {
            return $unitEntity->shortName === $unit;
        });

        if (!$foundUnit instanceof MeasurementDisplayUnitEntity) {
            throw MeasurementSystemException::unsupportedMeasurementUnit($unit, $availableUnits);
        }

        return $foundUnit;
    }

    public function reset(): void
    {
        $this->units = null;
    }

    public function getDecorated(): AbstractMeasurementUnitProvider
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return EntityCollection<MeasurementDisplayUnitEntity>
     */
    private function getUnits(): EntityCollection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        return $this->units = $this->measurementDisplayUnitRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }
}
