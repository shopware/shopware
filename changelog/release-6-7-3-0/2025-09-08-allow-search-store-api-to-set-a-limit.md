---
title: Allow Store-API search endpoints to set a limit again
issue: 6652
---
# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor::prepare` to allow usage of request parameter `limit` to set a custom limit for search Store-API requests. The limit is capped to the value of `shopware.api.store.max_limit` (default: 100).
___
# API
* Changed `/store-api/product-listing/{categoryId}`, `/store-api/search` and `/store-api/search-suggest` to allow usage of request parameter `limit` to set a custom limit for search store-api requests. The limit is capped to the value of `shopware.api.store.max_limit` (default: 100).
___
# Upgrade Information

## Re-allow setting a custom limit for search Store-API requests

The Store-API search endpoints `/store-api/product-listing/{categoryId}`, `/store-api/search` and `/store-api/search-suggest` now allow the usage of the request or query parameter `limit` to set a custom limit for search requests. The limit is capped to the value of `shopware.api.store.max_limit` (default: 100).