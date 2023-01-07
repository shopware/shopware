---
title: Optimize destruct calls
issue: NEXT-23008
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Storefront
* Added `AbstractReverseProxyGateway::flush` method, which is called in `ReverseProxyCache::__destruct`. This allows us better control of cache invalidation after a request has already sent 