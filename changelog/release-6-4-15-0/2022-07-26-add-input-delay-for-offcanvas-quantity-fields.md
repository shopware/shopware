---
title: Add input delay for offcanvas quantity fields
issue: NEXT-22595
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Added new JS-plugin options to `Resources/app/storefront/src/plugin/offcanvas-cart/offcanvas-cart.plugin.js`
    * Added option `changeProductQuantityTriggerNumberSelector`
    * Added option `changeQuantityInputDelay`
* Changed method `_registerChangeQuantityProductTriggerEvents` in `offcanvas-cart.plugin.js` and add separate handling for numeric input fields
* Changed `Resources/views/storefront/component/checkout/offcanvas-item.html.twig` and renamed selector inside block `component_offcanvas_product_buy_quantity_input` to `js-offcanvas-cart-change-quantity-number`
