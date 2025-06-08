---
title: Added cache for country route
issue: NEXT-14094
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\System\Country\SalesChannel\CachedCountryRoute`, which adds a cache for the store api country route
* Added `\Shopware\Core\System\Salutation\SalesChannel\CachedSalutationRoute`, which adds a cache for the store api salutation route
* Added `Request $request` parameter to `\Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute::load`
* Added `\Shopware\Core\Content\Product\SalesChannel\Review\CachedProductReviewRoute`, which adds a cache for the store api product review route
* Added `\Shopware\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute`, which adds a cache for the store api sitemap route
* Added `\Shopware\Core\Content\Sitemap\Event\SitemapGeneratedEvent`, which is dispatched when a sitemap was generated
