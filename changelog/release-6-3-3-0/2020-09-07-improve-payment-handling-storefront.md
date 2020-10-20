---
title:          Improve the overall user experience in the account order overview
issue:          NEXT-10487
author:         Tobias Berge
author_email:   t.berge@shopware.com
author_github:  @tobiasberge
---
# Storefront
* Added new variable `isPaymentNeeded` to `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig` which determines if the order has an unfinished payment.
* Added new const `ORDER_TRANSACTION_STATE_OPEN` to `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`.
* Added new const `ORDER_TRANSACTION_STATE_FAILED` to `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`.
* Added new const `ORDER_TRANSACTION_STATE_REMINDED` to `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`.
* Added new const `ORDER_STATE_CANCELLED` to `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`.
* Added new variant `badge-lg` to `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/component/_badge.scss`.
* Added new SCSS component `notification-dot` in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/component/_notification-dot.scss`.
* Added new SCSS file `order.scss` in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/page/account/_order.scss` for skin specific order styling.
* Added override of block `page_checkout_confirm_tos_control` in template `src/Storefront/Resources/views/storefront/page/account/order/index.html.twig` which extends from `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`.
