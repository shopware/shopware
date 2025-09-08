---
title: allow search store-api to set a limit
issue: 6652
---
# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor::prepare` to allow using request parameter `limit` to set a custom limit for search store-api requests. The limit is capped to a maximum of `shopware.api.store.max_limit` (default: 100).
___
# API
* Changed `/store-api/product-listing/{categoryId}`, `/store-api/search` and `/store-api/search-suggest` to allow using request parameter `limit` to set a custom limit for search store-api requests. The limit is capped to a maximum of `shopware.api.store.max_limit` (default: 100).
