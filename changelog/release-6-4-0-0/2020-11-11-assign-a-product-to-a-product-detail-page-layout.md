---
title: Assign a product to a Product Detail Page layout
issue: NEXT-11740
---
# Administration
* Added `headline` and `cmsPageTypes` props to `sw-cms-layout-modal` component.
* Added `sw-product-layout-assignment` component to `src/module/sw-product/component`.
* Added `sw_product_detail_base_layout` block to `src/module/sw-product/view/sw-product-detail-base/sw-product-detail-base.html.twig`.
* Changed `createdComponent()` method in `src/module/sw-product/page/sw-product-detail/index.js` to reset cms page state.
* Changed `product()` watch property in `src/module/sw-product/view/sw-product-detail-base/index.js` to load a cms page if needed.
