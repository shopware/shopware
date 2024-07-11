---
title: Add new context request attribute on customer login
issue: NEXT-36874
---
# Core
* Added listener on `CustomerLoginEvent` to `\Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber` in order to save the new Context to the request, which is later used in the subscriber to generate the cache state.
* Removed manually adding the context to the request in the `\Shopware\Storefront\Controller\AuthController` and replaced it with the listener, that should handle all cases (e.g. login, double-opt-in, etc).

