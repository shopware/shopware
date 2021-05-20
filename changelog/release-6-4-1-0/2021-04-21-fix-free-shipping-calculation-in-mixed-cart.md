---
title: Fix free shipping calculation in mixed cart
issue: NEXT-13140
author_github: @Dominik28111
---
# Core
* Added method `Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection::getWithoutDeliveryFree()` to exclude free delivery line items in calculation.
* Changed method `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::matches()` to use the method `getWithoutDeliveryFree()` for filtering.
