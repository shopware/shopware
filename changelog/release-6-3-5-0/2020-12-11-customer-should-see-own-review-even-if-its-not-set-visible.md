---
title: Customer should see his own review, even if it's not set visible
issue: NEXT-10590
---
# Core
* Added condition to Criteria to get the review of the customer is logged in and not set visible by admin
* Added condition to the criteria in the `handlePointsAggregation` method in `src/Storefront/Page/Product/Review/ProductReviewLoader.php` to get the review of the customer, who is logged in and whose rating is not set visible
___
# Storefront
* Added `page_product_detail_review_item_info_alert` block in `src/Storefront/Resources/views/storefront/page/product-detail/review/review-item.html.twig` to show the alert for the review of customer, who is logged in and whose rating is not set visible
