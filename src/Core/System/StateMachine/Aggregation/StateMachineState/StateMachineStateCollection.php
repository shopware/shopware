<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(StateMachineStateEntity $entity)
 * @method void                         set(string $key, StateMachineStateEntity $entity)
 * @method StateMachineStateEntity[]    getIterator()
 * @method StateMachineStateEntity[]    getElements()
 * @method StateMachineStateEntity|null get(string $key)
 * @method StateMachineStateEntity|null first()
 * @method StateMachineStateEntity|null last()
 */
class StateMachineStateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
