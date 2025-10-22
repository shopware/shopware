---
title: Cache layer for navigation loader
date: 2025-08-19
area: framework & discovery
tags: [performance, cache, categories]
---

## Context
We see in multiple performance analysis that the navigation loader can be a bottleneck for the performance of the storefront, especially with a huge number of categories in the first levels of the category tree.
The navigation loader is responsible for loading the categories and their children, which are then used to render the navigation in the storefront. 
This process can be quite expensive, not just because of the query time on the DB, but also for hydrating the entities into PHP objects.
The other part with a huge performance impact is the rendering performance on twig side for the many nested categories, however that is not part of this ADR.

The navigation loader is used in the storefront for every header and every listing page (when they use the sidebar navigation CMS element). 
However, the data that is loaded is always the same for the same sales channel, because we always show/load the category tree for the main navigation of the sales channel to the depth that is configured in the sales channel config.

Adding support for ESI for the header and footer addresses the same fundamental performance issue, however it does not solve the problem for the listing pages, where the sidebar navigation CMS element is used.
Additionally, for ESI to be effective you would need a reverse proxy that supports ESI, and you need to disable compression your responses from your webserver, which could increase your infrastructure costs. So ESI is not a viable solution for every use case.

## Decision
We will implement a cache layer for the navigation loader to improve the performance of the storefront.
To not only safe the query time, but also the hydration time, we will store the categories as PHP serialized objects. That should be fine, because when the structure of the PHP objects changes, that means there is a new platform version, in which case the cache needs to be cleared anyway.
Also in order to be most effective and not store too much data, we will only cache the category tree for the main navigation for every sales channel and up to the depths that is configured in the sales channel, because that info is loaded on every header and every listing page (when they use the sidebar navigation CMS element).
We use the `CacheCompressor` to compress the serialized data before storing it in the cache, which should reduce the size of the cache entries significantly, however it adds some more processing time when reading and writing the cache entries.

This cache layer will work complementary to ESI, because ESI would also cache the rendered HTML of the navigation, the performance impact of ESI will be faster, but category information for the sidebar navigation is still loaded on every listing page, so this change is still beneficial in regard to performance, even when you use ESI.

## Consequences
* The loaded categories for the main navigation will be cached to the level defined in the sales channel config.
* Additional categories that might be loaded because e.g. the currently active category is below the configured depth will be loaded dynamically per request and merged with the default categories.
* The cache needs to be invalidated whenever a category is written or deleted. We use immediate cache invalidation for that, so that the behaviour is as before, even with deactivated HTTP-Cache.
* We encode the information about the `salesChannelId`, `language`, `root category id` and `depth` in the cache key, so that we can cache the categories for different sales channels and languages.
* We will add a `CategoryLevelLoaderCacheKeyEvent` so that plugins can modify the cache key if they dynamically influence which categories should be loaded/shown.