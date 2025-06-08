---
title: Delete system config when deleting a sales channel
issue: NEXT-11442
---
# Core
* Added new migration `Migration1627541488AddForeignKeyForSalesChannelIdIntoSystemConfigTable` class at `Shopware\Core\Migration\V6_4` to delete system config of non-exists sales channel and add `sales_channel_id` foreign key into `system_config` table.
