---
title: Prevent redirect-route caching
issue: NEXT-17181
author: Daniel Beyer
author_email: d.beyer@shopware.com
---
# Core
* Changed `\Shopware\Storefront\Framework\Cache\CacheResponseSubscriber::setResponseCache` so that the route `frontend.account.login` will be allowed on login
