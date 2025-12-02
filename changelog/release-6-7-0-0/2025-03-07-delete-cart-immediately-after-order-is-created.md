---
title: Delete cart immediately after order is created
issue: NEXT-40611
---
# Core
* Changed `Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order` to delete the cart immediately after creating the order to avoid inconsistencies. Affects versions 6.8 and later
___
# Upgrade Information
## Cart will be deleted right after the order is created
Cart is no longer available in `CheckoutOrderPlacedCriteriaEvent` and `CheckoutOrderPlacedEvent`
