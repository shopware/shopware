<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineEntity>
 */
#[Package('core')]
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
