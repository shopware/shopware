<?php declare(strict_types=1); // @phpstan-ignore symplify.multipleClassLikeInFile

namespace Shopware\Core\Framework\Notification;

use Shopware\Administration\Notification\NotificationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

if (class_exists(\Shopware\Administration\Notification\NotificationCollection::class)) {
    /**
     * @deprecated tag:v6.8.0 - reason:class-hierarchy-change - Will not extend from `\Shopware\Administration\Notification\NotificationCollection` and will instead extend directly from `\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`.
     */
    #[Package('framework')]
    class NotificationCollection extends \Shopware\Administration\Notification\NotificationCollection
    {
        protected function getExpectedClass(): string
        {
            return NotificationEntity::class;
        }
    }
} else {
    /**
     * @extends EntityCollection<NotificationEntity>
     */
    #[Package('framework')]
    class NotificationCollection extends EntityCollection
    {
        protected function getExpectedClass(): string
        {
            return NotificationEntity::class;
        }
    }
}
