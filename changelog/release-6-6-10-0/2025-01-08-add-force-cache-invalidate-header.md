---
title: Add force cache invalidate header
issue: NEXT-31006
---
# Core
* Added `\Shopware\Core\PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE` const: 'sw-force-cache-invalidate'
* Changed `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidator::invalidate` to force the immediate Cache invalidation if the `PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE` header is set
___
# Next Major Version Changes
## Delayed Cache Invalidation
In the next major version, the cache invalidation will be delayed by default. This means that the cache will be invalidated in regular intervals and not immediately.
This will lead to better cache hit rates and way less (duplicated) cache invalidations, which will improve efficiency and scalability of the system.
As this feature is now active by default the previous `shopware.cache.invalidation.delay` configuration is removed.

The default interval is 5 min, this can be changed by adjusting the run interval of the `shopware.invalidate_cache` scheduled task.

If you sent an API request with critical information, where the cache should be invalidated immediately, you can set the `sw-force-cache-invalidate` header on your request.
```
POST /api/product
sw-force-cache-invalidate: 1
```

To manually clear all the stale caches you can either run the `cache:clear:delayed` command or use the `/api/_action/cache-delayed` API endpoint.
```
bin/console cache:clear:delayed
```
```
DELETE /api/_action/cache-delayed
```

For debugging there is the `cache:watch:delayed` command available, to watch the cache tags that are stored in the delayed cache invalidation queue.
```
bin/console cache:watch:delayed
```
