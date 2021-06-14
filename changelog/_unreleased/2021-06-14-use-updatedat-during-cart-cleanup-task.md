---
title: Use updatedAt during cart cleanup task.
issue: NEXT-16519
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler::run()`to take `updated_at` into account during scheduled cart cleanup task .
* Added migration `Shopware\Core\Migration\V6_4\Migration1623732234AddUpdatedAtIndexToCart` to compound index to `cart` table for `updated_at`.
