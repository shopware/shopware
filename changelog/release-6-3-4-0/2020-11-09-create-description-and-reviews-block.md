---
title: create "Description and Reviews" block
issue: NEXT-11743
---
# Administration
*  Added `sw-cms-block-product-description-reviews` component
*  Added `sw-cms-preview-product-description-reviews` component
*  Added `productDetailPageElements` data property in `module/sw-cms/page/sw-cms-detail/index.js` to store all product detail page's element names
*  Added `productDetailBlocks` data property in `module/sw-cms/page/sw-cms-detail/index.js` to store default product detail page's block config
*  Added `processProductDetailType()` method in `module/sw-cms/page/sw-cms-detail/index.js` to handle adding product detail page's blocks
*  Added `tooltipDisabled()` method in `module/sw-cms/page/sw-cms-sidebar/index.js` to temporarily show tooltip when layout type is product detail page
*  Changed `onPageTypeChange()` method in `module/sw-cms/page/sw-cms-detail/index.js` to handle adding blocks that are parts of product detail page
*  Removed `processHeadingBlock()` method in `module/sw-cms/page/sw-cms-detail/index.js` and use `processProductDetailType()` instead to handle adding product detail page's blocks
