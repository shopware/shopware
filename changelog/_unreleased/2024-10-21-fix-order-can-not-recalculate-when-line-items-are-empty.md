---
title: Fix order can not recalculate when line items are empty
issue: NEXT-39044
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\RecalculationService::recalculateOrder` to fix the issue with line items are empty.
