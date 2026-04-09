---
title: Add headers to vary Storefront API caches based on context vars
issue: https://github.com/shopware/shopware/issues/7881
---
# Core
* Added support for scoped response events `.scope.response` (like `storefront.scope.response`)
* Added `ContextAwareCacheHeadersSubscriber` to add `sw-currency-id`, `sw-language-id`, `sw-context-hash` to Storefront API responses, and `vary` header containing list of those headers.
* Changed `SalesChannelRequestContextResolver` to modify context currency based on `sw-currency-id` request header.
