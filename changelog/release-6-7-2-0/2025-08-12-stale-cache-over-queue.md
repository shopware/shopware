---
title: Stale cache over queue
---

# Core

* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStore` to dispatch messages to refresh http cache entries instead of doing it in the same time using `register_shutdown_function` to fix session related issues with the Symfony framework.
