---
title: Fix unable to change tax value in admin order
issue: NEXT-11803
---
# Administration
* Changed method `onItemChanged` in `module/sw-order/component/sw-order-product-select` to get `taxRate` from product `tax`.
* Changed method `getPayloadForItem` in `core/service/api/cart-store-api.api.service` to add `priceDefinition` to `item` when changing `taxRate` and add `quantity` to `priceDefinition` when changing `quantity` of `CUSTOM` product.
