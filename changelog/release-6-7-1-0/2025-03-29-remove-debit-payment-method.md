---
title: Deprecated payment method DebitPayment
author: Max Stegmeyer
author_github: mstegmeyer
---
# Core
* Deprecated payment handler `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment`
* Added migration to disable all payment methods using the deprecated payment handler `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment` for 6.8
___
# Next Major Version Changes

## Payment: Removal of Payment Method "Debit Payment"
The payment method `DebitPayment` has been removed as it did not fulfill its purpose.
If the payment method is and was not used, it will be removed.
Otherwise, the payment method will be disabled.
