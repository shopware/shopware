<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
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
