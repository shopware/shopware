---
title: Enforce afterOrderEnabled when changing an order's payment after checkout
issue: #17495
---
# Core
* Changed `Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` to reject payment methods whose `afterOrderEnabled` ("Allow payment change after checkout") flag is disabled, throwing `OrderException::paymentMethodNotChangeable()` (HTTP 403). Previously the flag was only applied as a UI filter on the edit-order page, so a payment method that renders its own JavaScript payment button (e.g. PayPal smart buttons) could still be used to pay an existing order via `POST /store-api/order/payment`.
