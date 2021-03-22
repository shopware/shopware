---
title: Replace the product media sidebar by a media modal
issue: NEXT-13291
---
# Administration
* Deprecated `onMediaUploadButtonOpenSidebar` method in `sw-product-media-form` component.
* Deprecated `sidebar-toggle-open` event listener in `sw-product-detail` component.
* Deprecated the following methods in `sw-product-detail` component:
    * `openMediaSidebar`
    * `onAddItemToProduct`
    * `addMedia`
    * `_checkIfMediaIsAlreadyUsed`
* Deprecated `sw_product_detail_sidebar` block in `sw-product-detail` component template.
* Changed `createdComponent` method in `sw-product-detail-base` component to get media default folder id.
* Added `sw_product_detail_base_media_modal` block in `sw-product-detail-base` component template.
