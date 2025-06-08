---
title: Incorrect tax calculation for shipping costs with automatic calculation with discount item
issue: NEXT-36490
---
# Core
* Added new property `shippingCostAware ` to `Shopware\Core\Checkout\Cart\LineItem\LineItem` to indicate if the line item is aware of the shipping costs. This property is used to calculate the tax for the shipping costs correctly.
* Changed method `process` in `Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor` to set the `shippingCostAware` property to `true` for the promotion line item. 
* Changed method `process` in `Shopware\Core\Content\Product\Cart\ProductCartProcessor` to set the `shippingCostAware` property to `true` or `false` for the product line item depending on the product is downloadable or not.
* Changed method `process` in `Shopware\Core\Checkout\Cart\CustomCartProcessor` to set the `shippingCostAware` property to `true` or `false` for the product line item depending on the product is downloadable or not.
* Changed method `process` in `Shopware\Core\Checkout\Cart\DiscountCartProcessor` to set the `shippingCostAware` property to `false` for the product line item.
* Changed method `process` in `Shopware\Core\Checkout\Cart\CreditCartProcessor` to set the `shippingCostAware` property to `false` for the product line item.
