---
title: Allow to disable caching of store-api-routes
issue: NEXT-23648
author: Simon Vorgers & Viktor Buzyka
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent` to allow to disable caching of store-api-routes
* Changed following Cached-Store-API-Routes to implement the new logic:
  * `Shopware\Core\Checkout\Payment\SalesChannel\CachedPaymentMethodRoute` 
  * `Shopware\Core\Checkout\Shipping\SalesChannel\CachedShippingMethodRoute` 
  * `Shopware\Core\Content\Category\SalesChannel\CachedCategoryRoute` 
  * `Shopware\Core\Content\Category\SalesChannel\CachedNavigationRoute` 
  * `Shopware\Core\Content\LandingPage\SalesChannel\CachedLandingPageRoute` 
  * `Shopware\Core\Content\Product\SalesChannel\CrossSelling\CachedProductCrossSellingRoute` 
  * `Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute` 
  * `Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute` 
  * `Shopware\Core\Content\Product\SalesChannel\Search\CachedProductSearchRoute` 
  * `Shopware\Core\Content\Product\SalesChannel\Suggest\CachedProductSuggestRoute` 
  * `Shopware\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute` 
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryRoute` 
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryStateRoute` 
  * `Shopware\Core\System\Currency\SalesChannel\CachedCurrencyRoute` 
  * `Shopware\Core\System\Language\SalesChannel\CachedLanguageRoute` 
  * `Shopware\Core\System\Salutation\SalesChannel\CachedSalutationRoute` 
___
# Upgrade Information
## Disabling caching of store-api-routes
The Cache for Store-API-Routes can now be disabled by implementing the `Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent` and calling `disableCache()` method on the event.
