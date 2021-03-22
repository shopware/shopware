---
title: Improved Payment Selection
issue: NEXT-9836
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Core
* Changed the sorting of payment and shipping method collections returned by its corresponding routes. The selected method will be sorted first, followed by the sales channels default method, followed by the current priority sorting. 
___
# Storefront
* Added `collapse-checkout-confirm-methods` JavaScript Plugin to handle the collapse of too many payments / shipping methods on the checkout confirm page.
* Added `page/account/order/confirm-payment.html.twig`.
* Added `page/account/order/confirm-shipping.html.twig`.
* Added `component/shipping/shipping-form.html.twig`.
* Added `component/shipping/shipping-fields.html.twig`.
* Added `component/shipping/shipping-method.html.twig`.
* Added `component/payment/payment-method.html.twig`.
* Changed `component/payment/payment-form.html.twig`.
* Changed `component/payment/payment-fields.html.twig`.
* Changed `page/checkout/confirm/confirm-payment.html.twig` and split up its content in multiple files under `component/payment/` to be much clearer.
* Changed `page/checkout/confirm/confirm-shipping.html.twig` and split up its content in multiple files under `component/shipping/` to be much clearer.
* Removed `page/account/order/payment.html.twig`.
* Removed `page/account/order/shipping.html.twig`.
* Removed `page/account/order/change-payment-modal.html.twig`.
* Removed `page_checkout_confirm_payment_current` and `page_checkout_confirm_payment_modal` blocks.
* Removed `page_checkout_confirm_shipping_current` and `page_checkout_confirm_shipping_modal` blocks.
___
# Upgrade Information
## Confirm checkout page / account edit order page
- On the `confirm checkout page` and `account edit order page`, the modal to change the payment or shipping method was removed.
- Instead, a maximum of `5` per default payment and shipping methods will be shown instantly.
- All other methods will be hidden under a JavaScript controlled collapse and may be triggered to appear by user interaction.
