---
title: Added live updates for cart changes in the Storefront
issue: NEXT-38859
---
# Storefront
* Added new Twig block `component_offcanvas_summary_cart_live_update` in `offcanvas-cart-summary.html.twig` that contains an alert element for reading out the most important cart information to screen reader users when the offcanvas cart is updated.
* Added new Twig block `page_checkout_summary_live_update` in `page/checkout/summary.html.twig` that contains an alert element for reading out the most important cart information to screen reader users when the cart is updated.
* Added new snippet `checkout.cartScreenReaderUpdate` for cart updates to screen reader users.
* Changed `quantity-selector.plugin.js` to only update the `aria-live` of the latest quantity change when the "onload" option is used.
* Changed the default value of the `autoFocus` option in `offcanvas-cart.plugin.js` to `false` for now. The focus change interferes with alert message updates in the cart.
* Changed the `autoFocus` option of `autoSubmitOptions` of the quantity select of line items in `quantity.html.twig` to `false`. The focus change interferes with alert message updates in the cart.

