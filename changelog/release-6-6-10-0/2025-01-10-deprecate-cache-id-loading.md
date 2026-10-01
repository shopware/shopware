---
title: Deprecate cache id loading
---

# Core

* Deprecated `\Shopware\Core\Framework\Adapter\Cache\CacheIdLoader`, when you want to change it you can still set `SHOPWARE_CACHE_ID` as environment variable to set the cache ID.

___

# Next Major Version Changes

## Cache ID loaded by Database is removed

Prior Shopware 6.7, the cache ID was loaded by the database from the `app_config` table and created complete different caches using that. This was used in earlier Shopware versions to clear the cache rapidly without having to clear the whole cache.
You can still set `SHOPWARE_CACHE_ID` as an environment variable to set the cache ID.

