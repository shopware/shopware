---
title: Handling tax-free in storefront
issue: NEXT-14117
---
# Core
* Changed the way to get `taxState` in function `calculate` at `Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator` class.
* Changed the way to get `taxState` in functions `calculate`, `calculateGrossAmount`, `calculateNetAmount`, `calculateTaxes` at `Shopware\Core\Checkout\Cart\Price\AmountCalculator` class.
* Added `getCartTaxType` function into `Shopware\Core\Checkout\Cart\CartRuleLoader` class.
___
# Administration
*  Changed `cartPrice.positionPrice` to `cart.price.totalPrice` in `sw_order_create_base_line_items_summary_amount_free_tax` block at `module/sw-order/view/sw-order-create-base/sw-order-create-base.html.twig`.
*  Changed `cartPrice.positionPrice` to `cart.price.totalPrice` in `sw_order_detail_base_line_items_summary_amount_free_tax` block at `module/sw-order/view/sw-order-create-base/sw-order-detail-base.html.twig`.
