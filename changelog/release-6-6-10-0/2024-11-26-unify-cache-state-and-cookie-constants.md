---
title: Unify cache state and cookie constants
issue: NEXT-39810
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated the following duplicate constants from `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber`. Use the ones with the same name from `Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber` instead.
  * `STATE_LOGGED_IN`
  * `STATE_CART_FILLED`
* Deprecated the following duplicate constants from `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber`. Use the ones with the same name from `Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator` instead.
  * `CURRENCY_COOKIE`
  * `CONTEXT_CACHE_COOKIE`
  * `SYSTEM_STATE_COOKIE`
  * `INVALIDATION_STATES_HEADER`
* Deprecated the following duplicate constant from `Shopware\Core\Framework\Script\Api\ScriptStoreApiRoute`. Use the one with the same name from `Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator` instead.
  * `INVALIDATION_STATES_HEADER`
___
# Next Major Version Changes
## Removal of deprecated constants
The following constants are removed from `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber`. Use the ones with the same name from `Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber` instead.
* `STATE_LOGGED_IN`
* `STATE_CART_FILLED`

The following constants are removed from `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber`. Use the ones with the same name from `Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator` instead.
* `CURRENCY_COOKIE`
* `CONTEXT_CACHE_COOKIE`
* `SYSTEM_STATE_COOKIE`
* `INVALIDATION_STATES_HEADER`

The following constants are removed from `Shopware\Core\Framework\Script\Api\ScriptStoreApiRoute`. Use the one with the same name from `Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator` instead.
* `INVALIDATION_STATES_HEADER`
