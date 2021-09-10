---
title: Correct edit line item when update order
issue: NEXT-13167
---
# Administration
* Added new method `cloneLineItems` in `src/module/sw-order/view/sw-order-detail-base/index.js` to clone the origin line items
* Changed method `onSaveEdits` in `src/module/sw-order/view/sw-order-detail-base/index.js` to make sure the order line items are original
