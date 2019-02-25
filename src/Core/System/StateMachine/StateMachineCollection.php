<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(StateMachineEntity $entity)
 * @method void                    set(string $key, StateMachineEntity $entity)
 * @method StateMachineEntity[]    getIterator()
 * @method StateMachineEntity[]    getElements()
 * @method StateMachineEntity|null get(string $key)
 * @method StateMachineEntity|null first()
 * @method StateMachineEntity|null last()
 */
class StateMachineCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineEntity::class;
    }
}
