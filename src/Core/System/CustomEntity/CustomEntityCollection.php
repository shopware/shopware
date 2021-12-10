<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(CustomEntityEntity $entity)
 * @method void                set(string $key, CustomEntityEntity $entity)
 * @method CustomEntityEntity[]    getIterator()
 * @method CustomEntityEntity[]    getElements()
 * @method CustomEntityEntity|null get(string $key)
 * @method CustomEntityEntity|null first()
 * @method CustomEntityEntity|null last()
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
