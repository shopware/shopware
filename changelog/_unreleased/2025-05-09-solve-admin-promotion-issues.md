---
title: Solve admin promotion issues
issue: #8649
---
# Core
* Changed `Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceEntity` properties `promotionDiscount` and `currency` to be nullable
___
# Administration
* Removed `src/module/sw-promotion-v2/component/sw-promotion-discount-component/sw-promotion-discount-component.html.twig` discount value digit option
* Removed `src/module/sw-promotion-v2/component/sw-promotion-discount-component/sw-promotion-discount-component.html.twig` discount max value help text condition
* Changed `Administration/src/module/sw-promotion-v2/component/sw-promotion-discount-component/index.js`.
  * Deprecated `maxValueAdvancedPricesTooltip`
  * Recalculate advanced prices, if discount value or max value has changed
