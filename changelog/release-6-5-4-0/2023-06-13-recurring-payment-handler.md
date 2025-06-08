---
title: Recurring payment handler
issue: NEXT-25815
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Added `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface` to handle recurring payment captures from subscriptions.
* Added `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry::getRecurringPaymentHandler` to retrieve the recurring payment handler for a given payment method.
* Added `Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor`, which is responsible for processing recurring payments and calling the payment handler.
* Added `Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler::captureRecurring`, which handles app payment method and calls the app endpoint with the recurring payload. 
* Added `Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct`, which is the payload sent to app endpoints during recurring capture for app payment methods.
* Added `Shopware\Core\Checkout\Payment\Exception\RecurringPaymentProcessException` to signalize errors occurring during recurring payment captures.
* Added `shopware.payment.method.recurring` service tag to allow plugins to add recurring payment methods.
* Added `recurring_url` to app manifests to allow apps to add a recurring captured payment method.
