---
title: Http Cache Reverse Proxy support
issue: NEXT-12958
---

# Storefront

* Added following new classes:
    * `Shopware\Storefront\DependencyInjection\ReverseProxyCompilerPass`
    * `Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway`
    * `Shopware\Storefront\Framework\Cache\ReverseProxy\RedisReverseProxyGateway`
    * `Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache`
* Added new configuration in the `storefront.yaml` for reverse http cache
