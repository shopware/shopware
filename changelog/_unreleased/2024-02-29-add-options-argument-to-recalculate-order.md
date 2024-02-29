---
title: add-options-argument-to-recalculate-order
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---
# Core
* Add `options` argument to `\Shopware\Core\Checkout\Cart\Order\RecalculationService::recalculateOrder` method. This allows to pass `options` when the `\Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` method is called. This is useful when you want to recalculate the order with different context options.
