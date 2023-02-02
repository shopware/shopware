<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomEntityEntity>
 */
class CustomEntityCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_entity_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomEntityEntity::class;
    }
}
