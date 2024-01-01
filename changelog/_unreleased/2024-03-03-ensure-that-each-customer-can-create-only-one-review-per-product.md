---
title: Ensure that each customer can create only one review per product
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute` to consider the correct property when validating that the product review for a customer already exists
* Changed the `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute` to not consider the name and mail from the request
