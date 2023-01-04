<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NotificationEntity>
 */
#[Package('administration')]
class NotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
