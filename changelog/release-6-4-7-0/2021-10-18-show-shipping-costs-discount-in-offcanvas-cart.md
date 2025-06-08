---
title: Show shipping costs discount in offcanvas cart
issue: NEXT-16805
author: Jakob Kruse
author_email: j.kruse@shopware.com
author_github: jakob-kruse
---

# Storefront
* Changed display of deliveries to iterate over every delivery in `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig` to include shipping costs discounts.
* Changed the price for shipping costs discounts to be hidden in the following files:
  - `src/Storefront/Resources/views/storefront/page/checkout/checkout-item.html.twig`
  - `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
___
# Core
* Changed mail templates to iterate of deliveries to show shipping costs discounts in files:
  - `src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/en-plain.html.twig`
