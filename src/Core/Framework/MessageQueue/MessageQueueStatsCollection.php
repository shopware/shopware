<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(MessageQueueStatsEntity $entity)
 * @method void                         set(string $key, MessageQueueStatsEntity $entity)
 * @method MessageQueueStatsEntity[]    getIterator()
 * @method MessageQueueStatsEntity[]    getElements()
 * @method MessageQueueStatsEntity|null get(string $key)
 * @method MessageQueueStatsEntity|null first()
 * @method MessageQueueStatsEntity|null last()
 */
class MessageQueueStatsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MessageQueueStatsEntity::class;
    }
}
