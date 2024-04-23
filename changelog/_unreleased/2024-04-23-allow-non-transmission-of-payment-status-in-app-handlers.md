---
title: Allow non-transmission of payment status for all App payment handlers
issue: NEXT-35567
author: Max Stegmeyer
---
# Storefront
* Changed `\Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler` to not do a state machine transition if the handler does not return a status.
