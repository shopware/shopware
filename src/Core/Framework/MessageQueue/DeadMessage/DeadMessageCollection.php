<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DeadMessageEntity>
 */
class DeadMessageCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_dead_message_collection';
    }

    protected function getExpectedClass(): string
    {
        return DeadMessageEntity::class;
    }
}
