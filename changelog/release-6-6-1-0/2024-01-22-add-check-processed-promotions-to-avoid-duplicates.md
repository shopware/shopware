---
title: Add check processed promotions to avoid duplicates
issue: NEXT-30360
author: Alexandru Dumea
author_email: a.dumea@shopware.com
author_github: Alexandru Dumea
---
# Core
* Added check for processed promotions in method `orderPlaced()` from class `Shopware/Core/Checkout/Promotion/DataAbstractionLayer/PromotionRedemptionUpdater.php` to avoid wrong incremental for `order_count` and `orders_per_customer_count`
