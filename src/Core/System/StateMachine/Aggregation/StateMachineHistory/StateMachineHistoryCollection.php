<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineHistoryEntity>
 */
#[Package('checkout')]
class StateMachineHistoryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_history_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineHistoryEntity::class;
    }
}
