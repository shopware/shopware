<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - use `shopware.increment.message_queue.gateway` service instead
 *
 * @extends EntityCollection<MessageQueueStatsEntity>
 */
class MessageQueueStatsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        return 'dal_message_queue_stats_collection';
    }

    protected function getExpectedClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        return MessageQueueStatsEntity::class;
    }
}
