<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package core
 * @extends EntityCollection<StateMachineEntity>
 */
class StateMachineCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineEntity::class;
    }
}
