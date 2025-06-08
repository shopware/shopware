---
title: Improve property cache invalidation queries
issue: NEXT-7479
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::getChangedPropertyFilterTags()` to perform multiple queries, instead of joining many tables, thus improving the performance.
