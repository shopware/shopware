<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:class-hierarchy-change - Will not extend from `\Shopware\Administration\Notification\NotificationCollection` and will instead extend directly from `\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`
 *
 * @extends \Shopware\Administration\Notification\NotificationCollection<NotificationEntity>
 */
#[Package('framework')]
class NotificationCollection extends \Shopware\Administration\Notification\NotificationCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
