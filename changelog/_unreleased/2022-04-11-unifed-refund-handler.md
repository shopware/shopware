---
title: Unifed refund handler
issue: NEXT-18543
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Implemented refund handling
* Added `Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition` to store captured transactions.
* Added `Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition` to store refunds of captured refunds.
* Added `Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition` to store a single position of refunds. 
* Added `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface` to implement for payment methods to enable refund handling.
* Added `Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor` to call payment refund handlers.
* Added various exceptions in the `Shopware\Core\Checkout\Payment\Exception\` namespace to throw on refund errors.
* Added method `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry::getRefundHandlerForPaymentMethod` to get a refund handler for a payment method
* Added runtime field `refundable` in `Shopware\Core\Checkout\Payment\PaymentMethodDefinition`, which will return `true` on refundable payment methods.
* Added state machine `Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates`.
* Added state machine `Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates`.
___
# API
* Added route `api.action.order.order_transaction_capture_refund` to handle refund requests.
