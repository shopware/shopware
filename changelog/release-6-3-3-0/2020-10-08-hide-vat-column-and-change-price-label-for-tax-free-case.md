---
title: Hide VAT column and change price label for tax free case
issue: NEXT-10984
---
# Administration
*  Added `unitPriceLabel()` computed property in `module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js`
*  Changed method `getLineItemColumns()` in `module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js` to correctly show price label based on tax status of Cart
*  Added `unitPriceLabel()` computed property in `module/sw-order/component/sw-order-line-items-grid/index.js`
*  Changed method `getLineItemColumns()` in `module/sw-order/component/sw-order-line-items-grid/index.js` to correctly show price label based on tax status of Order
*  Added conditional check using `taxStatus()` in `module/sw-order/view/sw-order-create-base/sw-order-create-base.html.twig` to display Total column base on tax status
*  Added conditional check using `taxStatus()` in `module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig` to display Total column base on tax status
