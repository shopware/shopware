---
title: Preserve focus of radio inputs for shipping and payment method selection in checkout
issue: NEXT-26705
---
# Storefront
* Added attribute `data-focus-id` to `payment-method-input` in `Resources/views/storefront/component/payment/payment-method.html.twig` so the focus is automatically resumed by the `focusHandler` after page reload. 
* Added attribute `data-focus-id` to `shipping-method-input` in `Resources/views/storefront/component/shipping/shipping-method.html.twig` so the focus is automatically resumed by the `focusHandler` after page reload.
* Changed element `confirm-checkout-collapse-trigger` from `<div>` to `<button>` in `Resources/views/storefront/component/shipping/shipping-fields.html.twig` to make it accessible via keyboard and screen-reader.
* Changed element `confirm-checkout-collapse-trigger` from `<div>` to `<button>` in `Resources/views/storefront/component/shipping/payment-fields.html.twig` to make it accessible via keyboard and screen-reader.
* Removed light text styling from `.shipping-method-description > p` in `Resources/app/storefront/src/scss/component/_shipping-method.scss` to improve text readability.