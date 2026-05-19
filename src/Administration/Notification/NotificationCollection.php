<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\Framework\Notification\NotificationCollection instead
 *
 * @extends EntityCollection<NotificationEntity>
 */
#[Package('framework')]
class NotificationCollection extends EntityCollection
{
}
