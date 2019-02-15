<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ScheduledTaskCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ScheduledTaskEntity::class;
    }
}
