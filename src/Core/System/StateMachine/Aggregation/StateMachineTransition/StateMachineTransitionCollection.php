<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(StateMachineTransitionEntity $entity)
 * @method void                              set(string $key, StateMachineTransitionEntity $entity)
 * @method StateMachineTransitionEntity[]    getIterator()
 * @method StateMachineTransitionEntity[]    getElements()
 * @method StateMachineTransitionEntity|null get(string $key)
 * @method StateMachineTransitionEntity|null first()
 * @method StateMachineTransitionEntity|null last()
 */
class StateMachineTransitionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineTransitionEntity::class;
    }
}
