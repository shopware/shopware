---
title: Fix review stars in administration
issue: NEXT-26290
---
# Administration
* Changed template `app/administration/src/module/sw-product/view/sw-product-detail-reviews/sw-product-detail-reviews.html.twig` and replaced custom implemented stars with `sw-rating-stars` component
    * Deprecated block `sw_product_detail_reviews_data_stars_filled`. Will be replaced by `sw-rating-stars` component. Use upper block `sw_product_detail_reviews_data_stars_content` instead.
    * Deprecated block `sw_product_detail_reviews_data_stars_empty`. Will be replaced by `sw-rating-stars` component. Use upper block `sw_product_detail_reviews_data_stars_content` instead.
