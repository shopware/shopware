---
title: Reset selection after removing grid items
issue: NEXT-18268
---
# Administration
* Added `ref` attribute for `sw-data-grid` in these components:
  * `src/module/sw-order/page/sw-order-list/sw-order-list.html.twig`
  * `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/sw-order-line-items-grid-sales-channel.html.twig`
* Changed these components to call `sw-data-grid`'s `resetSelection` method after removing one or many items from the grid:
  * `src/app/component/entity/sw-one-to-many-grid/index.js`
  * `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js`
  * `src/module/sw-order/page/sw-order-list/index.js`
  * `src/module/sw-product/component/sw-product-properties/index.js`
  * `src/module/sw-product/component/sw-product-variant-modal/index.js`
  * `src/module/sw-sales-channel/view/sw-sales-channel-detail-products/index.js`
