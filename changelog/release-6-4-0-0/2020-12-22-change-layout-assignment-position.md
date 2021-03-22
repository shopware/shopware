---
title: Change layout assignment position
issue: NEXT-12841
---
# Administration
* Added `subtitle` custom property in `sw-card` component.
* Added `sw_card_subtitle` block in `sw-card` component template to display a subtitle if needed.
* Added `sw.product.detail.layout` route in `sw-product` component.
* Added `sw_product_detail_content_tabs_layout` block in `sw-product-detail` component template to display layout tab.
* Added `sw-product-detail-layout` component in `src/module/sw-product/view/`.
* Deprecated `showLayoutModal` data in `sw-product-detail-base` component.
* Deprecated `onLayoutSelect` method in `product` watch property in `sw-product-detail-base` component.
* Deprecated the following computed properties in `sw-product-detail-base` component:
    * `cmsPageRepository`
    * `cmsPage`
* Deprecated the following methods in `sw-product-detail-base` component:
    * `openLayoutModal`
    * `closeLayoutModal`
    * `onLayoutSelect`
    * `openInPageBuilder`
    * `onLayoutReset`
