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

## Payment: Removal of Payment Method DebitPayment
The payment method `DebitPayment` has been removed as it did not fulfill its purpose.
All existing payment methods referencing the payment handler `DebitPayment` will be disabled.
New installations will not have the payment method `DebitPayment` installed.
