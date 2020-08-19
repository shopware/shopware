<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(AppEntity $entity)
 * @method void                  set(string $key, AppEntity $entity)
 * @method \Generator<AppEntity> getIterator()
 * @method array<AppEntity>      getElements()
 * @method AppEntity|null        get(string $key)
 * @method AppEntity|null        first()
 * @method AppEntity|null        last()
 */
class AppCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppEntity::class;
    }
}
