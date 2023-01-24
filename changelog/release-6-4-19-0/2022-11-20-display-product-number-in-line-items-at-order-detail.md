---
title: Improve the order detail by displaying product numbers in line items
issue: NEXT-12562
---
# Administration
* Added computed `isProductNumberColumnVisible` in `src/module/sw-order/component/sw-order-line-items-grid/index.js` to check visible with the product number.
* Changed computed `orderLineItems` in `src/module/sw-order/component/sw-order-line-items-grid/index.js` to config search product number.
* Changed computed `getLineItemColumns` in `src/module/sw-order/component/sw-order-line-items-grid/index.js` to add a product number column.
