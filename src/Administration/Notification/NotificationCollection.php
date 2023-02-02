<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NotificationEntity>
 */
class NotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
