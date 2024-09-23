---
title: Unify cache state and cookie constants
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated duplicate constants `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber::{STATE_LOGGED_IN,STATE_CART_FILLED}` use `Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::{STATE_LOGGED_IN,STATE_CART_FILLED}` instead
* Deprecated duplicate constants `Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber::{CURRENCY_COOKIE,CONTEXT_CACHE_COOKIE,SYSTEM_STATE_COOKIE,INVALIDATION_STATES_HEADER}` use `Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::{CURRENCY_COOKIE,CONTEXT_CACHE_COOKIE,SYSTEM_STATE_COOKIE,INVALIDATION_STATES_HEADER}` instead
