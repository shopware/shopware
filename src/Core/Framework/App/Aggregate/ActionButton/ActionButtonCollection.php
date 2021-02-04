<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * @method void                           add(ActionButtonEntity $entity)
 * @method void                           set(string $key, ActionButtonEntity $entity)
 * @method \Generator<ActionButtonEntity> getIterator()
 * @method array<ActionButtonEntity>      getElements()
 * @method ActionButtonEntity|null        get(string $key)
 * @method ActionButtonEntity|null        first()
 * @method ActionButtonEntity|null        last()
 */
class ActionButtonCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonEntity::class;
    }
}
