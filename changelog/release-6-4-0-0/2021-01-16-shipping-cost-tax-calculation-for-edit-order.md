---
title: Shipping costs tax calculation for editing order
issue: NEXT-10695
---
# Core
* Added `SHIPPING_METHOD_ID` in `\Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` to make sure shipping method always be in SaleChannelContext
