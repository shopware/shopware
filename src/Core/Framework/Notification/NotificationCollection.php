<?php declare(strict_types=1); // @phpstan-ignore symplify.multipleClassLikeInFile

namespace Shopware\Core\Framework\Notification;

use Shopware\Administration\Notification\NotificationCollection as AdminNotificationCollection;
use Shopware\Administration\Notification\NotificationEntity as AdminNotificationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Log\Package;

if (class_exists(AdminNotificationCollection::class)) {
    /**
     * @phpstan-ignore phpat.restrictNamespacesInCore (Don't do that! This will be fixed with the next major version as it is not used anymore)
     */
    #[Package('framework')]
    #[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend \Shopware\Administration\Notification\NotificationCollection but directly \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection.')]
    class NotificationCollection extends AdminNotificationCollection
    {
        protected function getExpectedClass(): string
        {
            /** @phpstan-ignore phpat.restrictNamespacesInCore */
            return AdminNotificationEntity::class;
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
