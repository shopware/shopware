---
title: Fix missing product link at a new order
issue: NEXT-28770
---
# Administration
* Changed `router-link` in `sw-order-line-items-grid-sales-channel` component by:
  * added attribute `_target`
  * changed params of attribute `to` from `id.productId` to `item.identifier || item.id`
