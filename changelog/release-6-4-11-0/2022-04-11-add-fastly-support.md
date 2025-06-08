---
title: Add fastly support
issue: NEXT-20683
---

# Storefront

* Added `\Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway` gateway to support invalidation and taggign for Fastly
* Changed `\Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway::tag` to accept an optional parameter `$response`. This parameter will required in 6.5
* Added new method `banAll` to `\Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway`. This method will be abstract in 6.5 
