---
title: Set NoAutoCacheControl on httpCache routtes
issue: NEXT-12019
---
# Storefront
* Added listener subscribe method  `setResponseCacheHeader` in `Shopware\Storefront\Framework\Cache\CacheResponseSubscriber` to set `$response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1')` if `_httpCache` is true on current route
* Removed set of `$response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1')` in `Shopware\Storefront\Controller\CmsController::filter`.
* Removed set of `$response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1')` in `Shopware\Storefront\Controller\ProductController::switch`.
* Removed set of `$response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1')` in `Shopware\Storefront\Controller\StorefrontController::renderStorefront`.
