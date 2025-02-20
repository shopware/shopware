---
title: A11y convey how the promotion information when submit
issue: NEXT-38716
---
# Storefront
* Added `aria-live="polite"` to `utilities_alert` block to convey the promotion information when submit in `views/storefront/utilities/alert.html.twig`.
* Changed `_onAddPromotionToCart` method to focus on the submit button after adding a promotion in `app/storefront/src/plugin/offcanvas-cart/offcanvas-cart.plugin.js`.
