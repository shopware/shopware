<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class StateMachineHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineHistoryEntity::class;
    }
}
