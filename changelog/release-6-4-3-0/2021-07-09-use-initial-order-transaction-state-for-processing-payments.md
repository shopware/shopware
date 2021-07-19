---
title: Use initial order transaction state for processing payments
issue: NEXT-15779
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Changed `\Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor::process` to use initial `OrderTransaction` state to process payments.
