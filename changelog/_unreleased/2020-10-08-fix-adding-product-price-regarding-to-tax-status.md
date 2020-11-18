---
title: Fix adding product price regarding to tax status
issue: NEXT-11190
---
# Administration
* Added computed property `taxStatus` in `module/sw-order/component/sw-order-line-items-grid/index.js`
* Added computed property `taxStatus` in `module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js`
* Added prop `taxStatus` in `sw-order-product-select` component
* Changed method `onItemChanged` in `module/sw-order/component/sw-order-product-select/index.js` to assign product price regarding to tax status
