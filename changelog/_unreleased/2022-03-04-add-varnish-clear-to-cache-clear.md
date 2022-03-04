---
title: Add varnish clear to cache:clear
issue: NEXT-19744
---
# Storefront

* Added new service `\Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer` to clear the cache of the external reverse proxy when the cache is cleard in the cli.
