---
title: Abstract and reduce rule condition components
issue: NEXT-20345
flag: V6_5_0_0
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `Shopware\Core\Framework\Rule\RuleConfig`
* Added method `Shopware\Core\Framework\Rule\Rule::getConfig()`
___
# Administration
* Added components `sw-condition-generic` and `sw-condition-generic-line-item`
* Added mixin `generic-condition`
* Deprecated the following components:
    * `sw-condition-billing-country`
    * `sw-condition-billing-street`
    * `sw-condition-cart-amount`
    * `sw-condition-cart-has-delivery-free-item`
    * `sw-condition-cart-position-price`
    * `sw-condition-cart-tax-display`
    * `sw-condition-currency`
    * `sw-condition-customer-group`
    * `sw-condition-customer-logged-in`
    * `sw-condition-customer-number`
    * `sw-condition-customer-tag`
    * `sw-condition-day-of-week`
    * `sw-condition-days-since-last-order`
    * `sw-condition-different-addresses`
    * `sw-condition-email`
    * `sw-condition-is-company`
    * `sw-condition-is-guest`
    * `sw-condition-is-new-customer`
    * `sw-condition-is-newsletter-recipient`
    * `sw-condition-language`
    * `sw-condition-last-name`
    * `sw-condition-line-item-actual-stock`
    * `sw-condition-line-item-clearance-sale`
    * `sw-condition-line-item-creation-date`
    * `sw-condition-line-item-dimension-height`
    * `sw-condition-line-item-dimension-length`
    * `sw-condition-line-item-dimension-volume`
    * `sw-condition-line-item-dimension-weight`
    * `sw-condition-line-item-dimension-width`
    * `sw-condition-line-item-in-product-stream`
    * `sw-condition-line-item-is-new`
    * `sw-condition-line-item-list-price`
    * `sw-condition-line-item-list-price-ratio`
    * `sw-condition-line-item-of-manufacturer`
    * `sw-condition-line-item-of-type`
    * `sw-condition-line-item-promoted`
    * `sw-condition-line-item-release-date`
    * `sw-condition-line-item-stock`
    * `sw-condition-line-item-tag`
    * `sw-condition-line-item-taxation`
    * `sw-condition-line-item-total-price`
    * `sw-condition-line-item-unit-price`
    * `sw-condition-line-items-in-cart-count`
    * `sw-condition-order-count`
    * `sw-condition-order-total-amount`
    * `sw-condition-payment-method`
    * `sw-condition-promotion-code-of-type`
    * `sw-condition-promotion-line-item`
    * `sw-condition-promotion-value`
    * `sw-condition-promotions-in-cart-count`
    * `sw-condition-sales-channel`
    * `sw-condition-shipping-country`
    * `sw-condition-shipping-method`
    * `sw-condition-shipping-street`
    * `sw-condition-volume-of-cart`
    * `sw-condition-weight-of-cart`

___
# Next Major Version Changes
## Deprecated rule condition components will be removed:
* If you used or extended any of these components, use/extend `sw-condition-generic` or `sw-condition-generic-line-item` instead and refer to `this.condition.type` to introduce changes for a specific type of condition. 
