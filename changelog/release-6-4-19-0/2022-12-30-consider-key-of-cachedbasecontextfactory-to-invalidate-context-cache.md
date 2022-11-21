---
title: Consider key of CachedBaseContextFactory to invalidate context cache
issue: NEXT-24759
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateContext()` to consider the key of `Shopware\Core\Framework\Adapter\Cache\CachedBaseContextFactory` to invalidate the context cache.
