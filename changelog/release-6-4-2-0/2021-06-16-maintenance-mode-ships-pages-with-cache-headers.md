---
title: Maintenance mode ships pages with cache headers
issue: NEXT-15578
---
# Core
* Changed function `setResponseCache` from `Shopware\Storefront\Framework\Cache\CacheResponseSubscriber` to prevent cache requests if IP in whitelist.
