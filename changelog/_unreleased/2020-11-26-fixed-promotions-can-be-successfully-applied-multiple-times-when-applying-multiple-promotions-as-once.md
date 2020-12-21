---
title: Fixed promotions can be successfully applied multiple times when applying multiple promotions as once
issue: NEXT-11595
---
# Core
*  Changed raw sql query in `\Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater::update` method with applying a `JSON_UNQUOTE` native function in WHERE condition to correctly search for matched promotion ids.
