---
title: Fix skip the recalculation of the order with tax-free
issue: NEXT-22956
---
# Core
* Changed function `recalculateOrder` in `Shopware\Core\Checkout\Cart\Order\RecalculationService` to replace `refresh` by `recalculateCart` to recalculate cart with rules.
* Changed function `assembleSalesChannelContext` in `\Shopware\Core\Checkout\Cart\Order\OrderConverter` to set `TaxState` for `salesChannelContext`.
