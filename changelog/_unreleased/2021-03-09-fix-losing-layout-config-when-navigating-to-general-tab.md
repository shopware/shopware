---
title: Fix losing layout config when navigating to general tab
issue: NEXT-14036
---
# Administration
* Changed watcher `product` in `src/module/sw-product/view/sw-product-detail-base/index.js` to prevent updating cmsPage when user navigate to general tab of product detail page
* Changed method `updateElementData` in `src/module/sw-cms/elements/image/config/index.js` to fix update element data
* Added watcher `product` in `src/module/sw-product/view/sw-product-detail-layout/index.js` to update mapping value when product is changed
* Changed style of `.sw-cms-el-config-image__mapping-preview img` in `src/module/sw-cms/elements/image/config/sw-cms-el-config-image.scss` to fix image blowing out its container on Chrome browser
