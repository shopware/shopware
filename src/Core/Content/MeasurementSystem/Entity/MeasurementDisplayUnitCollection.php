<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<MeasurementDisplayUnitEntity>
 */
#[Package('inventory')]
class MeasurementDisplayUnitCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MeasurementDisplayUnitEntity::class;
    }
}
