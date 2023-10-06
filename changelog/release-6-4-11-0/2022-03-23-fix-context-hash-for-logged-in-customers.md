---
title: Fix context hash for logged in customers
issue: NEXT-20691
---
# Storefront
* Changed `\Shopware\Storefront\Framework\Cache\CacheResponseSubscriber::buildCacheHash()` so that logged-in and not logged-in customers won't share the same context hash.
