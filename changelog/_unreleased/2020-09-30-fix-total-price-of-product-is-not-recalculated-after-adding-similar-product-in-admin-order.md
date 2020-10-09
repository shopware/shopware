---
title: Fix total price of product is not recalculate after adding similar product in admin order
issue: NEXT-10859
---
# Core
*  Changed the method `process` in `Shopware/Core/Content/Product/Cart/ProductCartProcessor` that `setQuantity` for `PriceDefinition` before recalculating the `totalPrice` when admin add a duplicated product to the new order.
