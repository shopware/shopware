---
title: Implement uploading and assigning variant images
issue: NEXT-14120
---
# Administration
* Added `media-default-folder` service in `src/app/service` to get a media default folder id if needed.
* Added the following methods in `sw-product-variants-media-upload` component:
    * `getMediaDefaultFolderId`
    * `onAddMedia`
    * `addMedia`
    * `isExistingMedia`
    * `onUploadMediaSuccessful`
    * `isReplacedMedia`
    * `onUploadMediaFailed`
* Added `sw_product_variants_media_upload_listener` block in `sw-product-variants-media-upload` component template.
* Changed `sw_product_variants_media_upload_actions_media_modal` block in `sw-product-variants-media-upload` component template.
