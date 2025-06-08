---
title: Prepared payment add pre-order call
issue: NEXT-17164
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Added `Shopware\Core\Checkout\Payment\PreparedPaymentService` to handle prepared payment calls from routes.
* Added `Shopware\Core\Checkout\Payment\Cart\PreparedPaymentProcessor` to call prepared payment handler.
* Changed `Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to call `validate` method of prepared payments before persisting the order.
* Changed `Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to call `capture` method of prepared payments before persisting the order.
* Added `Shopware\Core\Framework\App\Payment\Handler\AppPreparedPaymentHandler` allow prepared payments from apps.
* Added `validateUrl` and `captureUrl` to payment methods in app manifest.
