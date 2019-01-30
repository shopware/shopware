<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class StateMachineCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StateMachineEntity::class;
    }
}
