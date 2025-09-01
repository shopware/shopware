---
title: Multiple promotions order count fix
issue: #10774
---
# Core
* Changed calculation of promotion `order_count` and `orders_per_customer_count` in `Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater`. Uniform the calculation of these values. Promotions will be grouped by `order_id`, so multiple promotion line items per order will count as one usage.
___
# Administration
* Added grouped by promotion output in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-promotion-field/index.js`
