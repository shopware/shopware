---
title: Product review loader core
issue: NEXT-36468
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `ProductDescriptionReviewsCmsElementResolver` to use the `AbstractProductReviewLoader` and removed the duplicate functions
* Changed `ProductDescriptionReviewsCmsElementResolver` to now execute the Core `ProductReviewsWidgetLoadedHook` hook
* Added `AbstractProductReviewLoader` to allow overwriting product review load logic
* Added `ProductReviewLoader` based on the Storefront `ProductReviewLoader`
* Changed `ProductReviewResult` to include the `totalNativeReviews` field
* Added `ProductReviewsWidgetLoadedHook` based on the Storefront `ProductReviewsWidgetLoadedHook`
* Added `ProductReviewsLoadedEvent` based on the Storefront `ProductReviewsLoadedEvent`
* Added `Migration1711461585AddDefaultSettingConfigValueForReviewListingPerPage` to include the new config option
* Added `core.listing.reviewsPerPage` to config `listing` with default value `10`
* Changed `ProductDescriptionReviewsTypeDataResolverTest` to match Core changes
* Added `ProductReviewLoaderTest` to match core changes
___
# Storefront
* Changed `CmsController` to use `AbstractProductReviewLoader`
* Changed `ProductController` to use `AbstractProductReviewLoader`
* Changed `ProductReviewLoader` to `@deprecated` and copy logic from Core `ProductReviewLoader`
* Changed `ProductReviewsLoadedEvent` to `@deprecated`
* Changed `ProductReviewsWidgetLoadedHook` to `@deprecated`
* Changed `ReviewLoaderResult` to `@deprecated`
* Changed `review.html.twig` template to include the new `core.listing.reviewsPerPage` to config
* Changed `review.html.twig` template to include missing `nativeReviewsCount` and `foreignReviewsCount` variables
* Changed `review.html.twig` by including additional `component_review_list_action_filters` and `component_review_list_counter` blocks
* Changed `CmsControllerTest` to match Storefront changes
* Changed `ProductControllerTest` to match Storefront changes
* Changed `ProductReviewLoaderTest` to match Storefront changes
