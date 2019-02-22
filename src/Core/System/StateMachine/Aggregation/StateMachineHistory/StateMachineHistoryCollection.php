<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(StateMachineHistoryEntity $entity)
 * @method void                           set(string $key, StateMachineHistoryEntity $entity)
 * @method StateMachineHistoryEntity[]    getIterator()
 * @method StateMachineHistoryEntity[]    getElements()
 * @method StateMachineHistoryEntity|null get(string $key)
 * @method StateMachineHistoryEntity|null first()
 * @method StateMachineHistoryEntity|null last()
 */
class StateMachineHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineHistoryEntity::class;
    }
}
