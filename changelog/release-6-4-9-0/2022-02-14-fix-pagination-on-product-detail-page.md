---
title: Fix pagination on Product Detail Page
issue: NEXT-10807
---
# Storefront
* Changed behaviour of Product Detail Page to consider the total amount of its reviews instead of just the given limit. This affects:
  * `Storefront/Page/Product/Review/ProductReviewLoader.php`
  * `Storefront/Resources/views/storefront/page/product-detail/review/review.html.twig`
* Changed behaviour of `Storefront/Resources/views/storefront/component/pagination.html.twig` to allow `total` as an optional parameter
* Changed sorting property of Product Detail Page Reviews to `createdAt`, to show the newest comments first