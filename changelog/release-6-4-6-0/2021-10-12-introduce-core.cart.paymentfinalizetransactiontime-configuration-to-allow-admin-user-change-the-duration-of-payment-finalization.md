---
title: Introduce core.cart.paymentFinalizeTransactionTime configuration to allow admin user change the duration of payment finalization
issue: NEXT-17956
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---

# Core
* Added configuration `core.cart.paymentFinalizeTransactionTime` to allow configuration based token lifetime generated in `\Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor::process` which defaults to 30 minutes
