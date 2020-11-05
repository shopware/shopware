---
title: Do not set http cache cookies if http cache is disabled
issue: NEXT-11075
___
# Storefront
* Changed the `\Shopware\Storefront\Framework\Cache\CacheResponseSubscriber` to not set http cache headers if the http cache is disabled
