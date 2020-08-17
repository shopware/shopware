<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
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
