<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class StateMachineStateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
