---
title: Add condition number of reviews
issue: NEXT-22926
author: p.dinkhoff

---
# Core
* Added rule condition `NumberOfReviewsRule`
* Added field `reviewCount` to `CustomerDefinition`
* Added `Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber` to update the `reviewCount`
