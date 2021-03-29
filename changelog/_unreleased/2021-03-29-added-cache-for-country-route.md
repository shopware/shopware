---
title: Added cache for country route
issue: NEXT-14094
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\System\Country\SalesChannel\CachedCountryRoute`, which adds a cache for the store api country route
* Added `Request $request` parameter to `\Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute::load`
