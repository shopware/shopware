---
title: Invalidate parent products when invalidating product detail route
issue: NEXT-29733
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateDetailRoute` to also clear the detail route for parent products of variants
