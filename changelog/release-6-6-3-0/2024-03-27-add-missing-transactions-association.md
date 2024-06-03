---
title: Add missing transactions association
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Storefront
* Added missing transactions association when using the `\Shopware\Storefront\Controller\AccountOrderController::updateOrder` method. The `\Shopware\Core\Checkout\Order\SalesChannel\OrderService::isPaymentChangeableByTransactionState` method will always return true since the transactions are not loaded on the order.

