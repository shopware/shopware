<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(ScheduledTaskEntity $entity)
 * @method void                     set(string $key, ScheduledTaskEntity $entity)
 * @method ScheduledTaskEntity[]    getIterator()
 * @method ScheduledTaskEntity[]    getElements()
 * @method ScheduledTaskEntity|null get(string $key)
 * @method ScheduledTaskEntity|null first()
 * @method ScheduledTaskEntity|null last()
 */
class ScheduledTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_scheduled_task_collection';
    }

    protected function getExpectedClass(): string
    {
        return ScheduledTaskEntity::class;
    }
}
