---
title: generate http cache key when cart contains items
issue: NEXT-12330
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Storefront
* Changed `\Shopware\Storefront\Framework\Cache\CacheResponseSubscriber`, the cache key cookie is now also generated if the customer has items in the shopping cart
