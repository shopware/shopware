---
title: Fix preview mapping of image gallery in product detail page
issue: NEXT-14036
---
# Administration
* Changed method `addMedia` in `src/module/sw-product/view/sw-product-detail-base/index.js` to add media url into new media of product
* Added method `createdComponent` in `src/module/sw-product/view/sw-product-detail-layout/index.js` to handle getting product page layout configuration
* Changed class `.sw-cms-sidebar__block-preview img` in `src/module/sw-cms/component/sw-cms-sidebar/sw-cms-sidebar.scss` to fix image blowing out on Chrome
