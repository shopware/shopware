---
title: Fix Cache Cookie Handling to prevent cache poisoning
issue: #12756
---

# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::generate()` to accept an optional Response object, when the cache key is generated to store the response in the cache. 
  When the response is set, the cache cookies are first checked on the response object, as those might differ from the request cookies, and the request cookies can be manipulated by the client. This prevents cache poisoning when the request cookies are not sent correctly.
* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator::isValid()` to also check the response cookies first for the same reason. 
