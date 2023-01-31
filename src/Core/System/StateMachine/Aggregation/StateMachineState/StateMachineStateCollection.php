<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineStateEntity>
 */
#[Package('checkout')]
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
