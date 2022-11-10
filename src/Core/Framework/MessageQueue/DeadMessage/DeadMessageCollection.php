<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DeadMessageEntity>
 *
 * @deprecated tag:v6.5.0 - reason:remove-entity - Will be removed, as we use the default symfony retry mechanism
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
