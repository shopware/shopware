---
title: Fix `totalReviewCount` with matrix total
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult` to deprecate `getTotalReviews` & `setTotalReviews`
___
# Storefront
* Changed `views/storefront/component/review/review.html.twig` to use `reviews.matrix.totalReviewCount` for new `totalReviewCount` variable
* Changed `views/storefront/component/review/review-widget.html.twig` to use `totalReviewCount` variable for `itemprop` & `filter` checks
