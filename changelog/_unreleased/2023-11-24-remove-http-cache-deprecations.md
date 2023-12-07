---
title: Remove http cache deprecations
issue: NEXT-30261
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Removed `Shopware\Storefront\Framework\Cache\ReverseProxy\*`, moved it to `Shopware\Core\Framework\Adapter\Cache\ReverseProxy\*`
* Removed `storefront.reverse_proxy` and `storefront.http_cache`, now stored under `shopware.http_cache.reverse_proxy` and `shopware.http_cache`
* Changed `\Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel::__construct` signature
* Removed `Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\CacheInvalidatorStorage`
* Removed `Shopware\Core\Framework\Api\Controller\CacheController::clearCacheAndScheduleWarmUp` 
* Removed `/api/_action/cache_warmup` endpoint
