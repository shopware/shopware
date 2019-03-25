<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(StoreSettingsEntity $entity)
 * @method void                     set(string $key, StoreSettingsEntity $entity)
 * @method StoreSettingsEntity[]    getIterator()
 * @method StoreSettingsEntity[]    getElements()
 * @method StoreSettingsEntity|null get(string $key)
 * @method StoreSettingsEntity|null first()
 * @method StoreSettingsEntity|null last()
 */
class StoreSettingsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StoreSettingsEntity::class;
    }
}
