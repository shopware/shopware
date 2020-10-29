---
title: Remove price preview for product items in admin orders
issue: NEXT-10697
---
# Administration
* Changed method `initLineItem` in `module/sw-order/component/sw-order-line-items-grid-sales-channel` to remove price preview when adding a product, custom item or credit in Admin order.
* Changed method `createNewOrderLineItem` in `module/sw-order/component/sw-order-line-items-grid` to remove price preview when adding a product, custom item or credit in Edit order.
* Changed method `onItemChanged` in `module/sw-order/component/sw-order-product-select` to remove price preview when selecting a product in Admin/Edit order.
