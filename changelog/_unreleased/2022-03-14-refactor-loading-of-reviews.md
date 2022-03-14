---
title: Refactor loading of reviews
issue: NEXT-00000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added core loader to loading reviews `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader`
* Added `Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` and `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult`
* Changed loading of reviews in `Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver` and `Shopware\Storefront\Page\Product\Review\ProductReviewLoader` using the `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader`
___
# Storefront
* Deprecated `Shopware\Storefront\Page\Product\Review\ProductReviewLoader` use `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader` instead
* Deprecated `Shopware\Storefront\Page\Product\Review\ProductReviewsLoadedEvent` use `Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` instead
* Deprecated `Shopware\Storefront\Page\Product\Review\ReviewLoaderResult`
