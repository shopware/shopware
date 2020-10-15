---
title: Improve payment selection deprecations
issue: NEXT-9836
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Storefront
* Changed payment selection templates in upcoming **v6.4.0.0** release
___
  
# Upgrade Information
The upcoming **6.4.0.0** release will contain major **breaking changes** to the payment and shipping method selection templates in the storefront.
The modal to select payment or shipping methods was removed entirely.
Instead, the payment and shipping methods will be shown instantly up to a default maximum of `5` methods.
All other methods will be hidden inside a JavaScript controlled collapse.

The changes especially apply to the `confirm checkout` and `edit order` pages.

We refactored most of the payment and shipping method storefront templates and split the content up into multiple templates to raise the usability.

**Please review the changes on the `major` branch on GitHub.**  

## Breaking changes in upcoming v6.4.0.0 release:

`storefront/page/checkout/confirm/confirm-payment.html.twig`:
 * Renamed block `page_checkout_confirm_payment_current` to `page_checkout_change_payment_form`. This block will include the new component `storefront/component/payment/payment-form.html.twig` which will hold the contents.
 * Removed block `page_checkout_confirm_payment_current_image`.
 * Removed block `page_checkout_confirm_payment_current_text`.
 * Removed block `page_checkout_confirm_payment_invalid_tooltip`.
 * Removed block `page_checkout_confirm_payment_modal_toggle`.
 * Removed block `page_checkout_confirm_payment_modal`.
 * Removed block `page_checkout_confirm_payment_modal_body`.

`storefront/page/checkout/confirm/confirm-shipping.html.twig`:
 * Renamed block `page_checkout_confirm_shipping_current` to `page_checkout_change_shipping_form`. This block will include the new component `storefront/component/shipping/shipping-form.html.twig` which will hold the contents.
 * Moved content of block `page_checkout_confirm_shipping_form` to the new components.
 * Removed block `page_checkout_confirm_shipping_current_image`.
 * Removed block `page_checkout_confirm_shipping_current_text`.
 * Removed block `page_checkout_confirm_shipping_invalid_tooltip`.
 * Removed block `page_checkout_confirm_shipping_modal_toggle`.
 * Removed block `page_checkout_confirm_shipping_modal`.
 * Removed block `page_checkout_confirm_shipping_modal_body`.

`storefront/component/payment/payment-fields.html.twig`:
 * Moved content of block `component_payment_method` to its own new template `storefront/component/payment/payment-method.html.twig`.

Added following templates:
 * `storefront/component/payment/payment-form.html.twig`.
 * `storefront/component/payment/payment-method.html.twig`.
 * `storefront/component/shipping/shipping-form.html.twig`.
 * `storefront/component/shipping/shipping-fields.html.twig`.
 * `storefront/component/shipping/shipping-method.html.twig`.
 * `storefront/page/account/order/confirm-payment.html.twig`.
 * `storefront/page/account/order/confirm-shipping.html.twig`.

Removed following templates:
 * `storefront/page/account/order/payment.html.twig`.
 * `storefront/page/account/order/shipping.html.twig`.
 * `storefront/page/account/order/change-payment-modal.html.twig`.
