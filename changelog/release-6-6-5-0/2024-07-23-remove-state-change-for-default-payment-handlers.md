---
title: Removed automatic state change for direct debit default payment
issue: NEXT-37336
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Deprecated automatic state change for `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment`
___
# Next Major Version Changes
## Direct debit default payment: State change removed
* The default payment method "Direct debit" will no longer automatically change the order state to "in progress". Use the flow builder instead, if you want the same behavior.
