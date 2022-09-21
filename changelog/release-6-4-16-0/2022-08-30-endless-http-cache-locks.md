---
title: Endless http cache locks
issue: NEXT-23053
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Storefront
* Changed `\Shopware\Storefront\Framework\Cache\CacheStore::lock`, to always set an expires date for the lock cache item.