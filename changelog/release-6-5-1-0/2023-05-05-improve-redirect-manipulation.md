---
title: Improve redirect manipulation
issue: NEXT-25788
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Storefront
* Added error route manipulation for `RegisterController` and `component/account/register.html.twig`
* Added `redirectParameter` input fields for
  * `component/shipping/shipping-form.html.twig`
  * `component/line-item/element/quantity.html.twig`
  * `component/line-item/element/remove.html.twig`
  * `page/cehckout/confirm/confirm-payment.html.twig`
  * `page/checkout/confirm/confirm-shipping.html.twig`
* Added blocks for form action URL manipulation in
  * `component/line-item/element/quantity.html.twig`
  * `component/line-item/element/remove.html.twig`
  * `page/checkout/confirm/index.html.twig`
  * `page/checkout/confirm/confirm-payment.html.twig`
* Deprecated block `page_product_detail_buy_form_action` in `page/product-detail/buy-widget-form.html.twig`, overwrite variable `formAction` to manipulate the form action
* Deprecated block `buy_widget_buy_form_action` in `component/buy-widget/buy-widget-form.html.twig`, overwrite variable `formAction` to manipulate the form action
