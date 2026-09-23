<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NotificationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[ClassMoved(version: 'v6.8.0', previousClassName: 'Shopware\Administration\Notification\NotificationCollection')]
class NotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
