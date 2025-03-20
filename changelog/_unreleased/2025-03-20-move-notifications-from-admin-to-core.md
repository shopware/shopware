---
title: Move notifications from admin to core
---
# Core
* Added `Shopware\Core\Framework\Notification\NotificationCollection`, `Shopware\Core\Framework\Notification\NotificationDefinition` & `Shopware\Core\Framework\Notification\NotificationEntity`
___
# Administration
* Deprecated `Shopware\Administration\Notification\NotificationCollection`, `Shopware\Administration\Notification\NotificationDefinition` & `Shopware\Administration\Notification\NotificationEntity`
___
# Upgrade Information
## Deprecated admin notification entity + related classes

We have moved the notification entity, collection and definition to core. You should update your code to reference the new classes:

* `Shopware\Core\Framework\Notification\NotificationCollection`
* `Shopware\Core\Framework\Notification\NotificationDefinition`
* `Shopware\Core\Framework\Notification\NotificationEntity`
___
# Next Major Version Changes
## Removed admin notification entity + related classes

You should update your code to reference the new classes:

* `Shopware\Core\Framework\Notification\NotificationCollection`
* `Shopware\Core\Framework\Notification\NotificationDefinition`
* `Shopware\Core\Framework\Notification\NotificationEntity`
