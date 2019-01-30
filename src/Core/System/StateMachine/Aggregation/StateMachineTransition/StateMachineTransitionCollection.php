<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class StateMachineTransitionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineTransitionEntity::class;
    }
}
