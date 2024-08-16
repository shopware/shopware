---
title: Cache tagging
issue: NEXT-31669
author: oskroblin Skroblin
author_email: goblin.coders@gmail.com
---

# Core
* Deprecated all `Cached*Route` store api routes. Cache tagging is moved to the underlying route class.
* Deprecated `SortedPaymentMethodRoute` route, sorting is now done in the `PaymentMethodRoute` route
* Deprecated `SortedShippingMethodRoute` route, sorting is now done in the `ShippingMethodRoute` route
* Added `InvalidateProductCache` event, which allows invalidating the cache for a list of product ids
* Added `HttpCacheCookieEvent` event, which allows controlling the data for the http cache cookie
* Added `AddCacheTagEvent` event, which allows adding cache tags to the response by dispatching this event
* Deprecated `CacheDecorator` class, use `CacheTagCollector` instead to access the cache tags for a single request
* Deprecated different small cache event listener functions, which invalidates the cache for product entities and group them to a new `invalidateProduct` function, following functions were deprecated:
  * `CacheInvalidationSubscriber::invalidateProductIds`
  * `CacheInvalidationSubscriber::invalidateSearch`
  * `CacheInvalidationSubscriber::invalidateDetailRoute`
  * `CacheInvalidationSubscriber::invalidateProductAssignment`
  * `CacheInvalidationSubscriber::invalidateReviewRoute`
  * `CacheInvalidationSubscriber::invalidateListings`
  * `CacheInvalidationSubscriber::invalidateStreamsAfterIndexing`
  * `CacheInvalidationSubscriber::invalidateCrossSellingRoute`
  * `CacheInvalidationSubscriber::invalidateProduct`
  * `CacheInvalidationSubscriber::invalidateProduct`
* Added new `shopware.http_cache.cookies` config, which allows configuring the cookies for the http cache cookie
* Changed `HttpCacheStoreEvent`, in 6.7, the event will be triggered before the cache item will be saved 
