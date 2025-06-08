---
title: Added payment token invalidation
issue: NEXT-14739
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added new `\Shopware\Core\Checkout\Payment\Exception\TokenInvalidatedException` which is thrown if the payment token already used to finalize a transaction.
* Changed `\Shopware\Core\Checkout\Payment\Controller\PaymentController::finalizeTransaction` exception handling, all `ShopwareHttpException` are now redirected to the provided error url 
