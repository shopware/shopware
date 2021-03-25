---
title: Add cross selling and category route cache
issue: NEXT-14084
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `CategoryRouteCacheKeyEvent` which is used to generate the cache key for the category route
* Added `CategoryRouteCacheTagsEvent` which is used to generate the cache tags for the category route
* Added `CachedCategoryRoute` which adds a cache layer around the `store-api.category` route
* Added `ProductStreamMappingDefinition` which contains a mapping, which product is affected by which product stream
* Added `ProductStreamMappingIndexingMessage` which is used to generate the mapping between products and the affected product streams
* Added `ProductStreamUpdater` which updates the mapping between products and the affected product streams
* Added `CrossSellingRouteCacheKeyEvent` which is used to generate the cache key for the cross selling route
* Added `CrossSellingRouteCacheTagsEvent` which is used to generate the cache tags for the cross selling route
* Added `ProductChangedEventInterface` which is used to use the same event listener for different product events. 
* Added `ProductNoLongerAvailableEvent` which is triggered when the `product.available` flag switched to false
* Added `CachedProductCrossSellingRoute` which adds a cache layer around the `store-api.cross-selling` route
* Added `ProductSliderStruct.php::streamId` property
* Added `ProductEntity::streams` property
* Added `CrossSellingElement.php::streamId` property
* Moved src/Storefront/Framework/Cache/CacheDecorator.php to src/Core/Framework/Adapter/Cache/CacheDecorator.php   
* Moved src/Storefront/Framework/Cache/CacheTagCollection.php to src/Core/Framework/Adapter/Cache/CacheTagCollection.php
* Changed signature of `AbstractProductCrossSellingRoute::load` function: `load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductCrossSellingRouteResponse;`
