<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\Framework\Notification\NotificationDefinition instead
 */
#[Package('framework')]
abstract class NotificationDefinition extends EntityDefinition
{
}
