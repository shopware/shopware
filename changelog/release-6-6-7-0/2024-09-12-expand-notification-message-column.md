---
title: Expand notification message column to allow longer messages
issue: NEXT-38266
---
# Administration
* Changed field type of `message` column in `\Shopware\Administration\Notification\NotificationDefinition` to `LongText` to allow longer messages.
* Added `\Shopware\Administration\Migration\V6_6\Migration1726132532ExpandNotificationMessage` migration to update the column type from varchar to longtext.