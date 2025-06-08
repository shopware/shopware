---
title: Create product reviews tab
issue: NEXT-13288
---
# Administration
* Added `sw-product-detail-reviews` component in `view` folder of `sw-product` module.
* Added `sw.product.detail.reviews` route in `sw-product` module.
* Added `sw_product_detail_content_tabs_reviews` block in `sw-product-detail` component template.
* Deprecated `sw_product_detail_base_ratings_card` block in `sw-product-detail-base` component template.
* Deprecated `reviewColumns` computed property in `sw-product-detail-base` component.
* Deprecated the following data variables in `sw-product-detail-base` component:
    * `showReviewDeleteModal`
    * `toDeleteReviewId`
    * `reviewItemData`
    * `page`
    * `limit`
    * `total`
* Deprecated the following methods in `sw-product-detail-base` component:
    * `createdComponent`
    * `onStartReviewDelete`
    * `onConfirmReviewDelete`
    * `onCancelReviewDelete`
    * `onShowReviewDeleteModal`
    * `onCloseReviewDeleteModal`
    * `reloadReviews`
    * `onChangePage`
* Deprecated the following CSS blocks in `sw-product-detail-base.scss`:
    * `sw-review-detail-base__stars`
    * `sw-product-detail-base__review-card`
