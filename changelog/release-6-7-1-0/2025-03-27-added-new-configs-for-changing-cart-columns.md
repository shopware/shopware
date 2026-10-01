---
title: Added new configs for changing cart columns
issue: https://github.com/shopware/shopware/issues/7482
author_github: @En0Ma1259
---
# Core
* Added new `core.cart.showSubtotal` config to toggle cart subtotal column
* Added new `core.cart.columnTaxInsteadUnitPrice` config to switch between "tax" and "unit price" cart column for oder confirmation page
___
# Storefront
* Added check for new config `core.cart.showSubtotal` in `src/Storefront/Resources/views/storefront/component/checkout/cart-header.html.twig`
* Added new config to hide total field in
  * `src/Storefront/Resources/views/storefront/component/line-item/type/container.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/discount.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/generic.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/product.html.twig`
* Added read new configs in
  * `src/Storefront/Resources/views/storefront/page/account/order/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/cart/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/finish/index.html.twig`
