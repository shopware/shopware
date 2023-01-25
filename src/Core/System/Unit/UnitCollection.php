<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UnitEntity>
 */
#[Package('core')]
class UnitCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'unit_collection';
    }

    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
