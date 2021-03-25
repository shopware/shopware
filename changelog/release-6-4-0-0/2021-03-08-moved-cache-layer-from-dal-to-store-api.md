---
title: Moved cache layer from DAL to store api
issue: NEXT-11735
author: OliverSkroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Core
* Changed caching mechanic inside the core. The cache layer was moved from the DAL to the store api routes and some other internal services
* Added cache layer for rule loading
    * Added `AbstractRuleLoader`, which is the base class for the `RuleLoader`
    * Added `CachedRuleLoader`, which adds a cache around the rule loading
* Added cache layer for request domain resolving
    * Added `AbstractDomainLoader`, which is the base class for the `DomainLoader`
    * Added `CachedDomainLoader`, which adds a cache around the domain loading
    * Added `CachedDomainLoaderInvalidator`, which invalidates the cache for the domain loading
    * Added `DomainLoader`, which is responsible to provide all existing domains. This domains are used to detect which domain is requested in an incoming request
* Added cache layer for shipping method store api route
    * Added `CachedShippingMethodRoute`, which adds a cache around the shipping method loading
    * Added `ShippingMethodRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `ShippingMethodRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined 
* Added cache layer for product suggest store api route
    * Added `CachedProductSuggestRoute`, which adds a cache around the product suggest route
    * Added `ProductSuggestRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `ProductSuggestRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for product search store api route
    * Added `CachedProductSearchRoute`, which adds a cache around the product search route
    * Added `ProductSearchRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `ProductSearchRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
    * Added `ResolvedCriteriaProductSearchRoute`, which resolves the criteria before the cached route is called. This way the route gets a resolved criterion and does not have to listen to all request parameters. 
* Added cache layer for product listing store api route
    * Added `CachedProductListingRoute`, which adds a cache around the product listing route
    * Added `ProductListingRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `ProductListingRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
    * Added `ResolveCriteriaProductListingRoute`, which resolves the criteria before the cached route is called. This way the route gets a resolved criterion and does not have to listen to all request parameters. 
* Added cache layer for product detail store api route
    * Added `CachedProductDetailRoute`, which adds a cache around the product detail route
    * Added `ProductDetailRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `ProductDetailRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for payment method store api route
    * Added `CachedPaymentMethodRoute`, which adds a cache around the payment method loading of a sales channel 
    * Added `PaymentMethodRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `PaymentMethodRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for navigation store api route
    * Added `CachedNavigationRoute`, which adds a cache around the navigation loading
    * Added `NavigationRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `NavigationRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for language store api route
    * Added `CachedLanguageRoute`, which adds a cache around the language loading of a sales channel
    * Added `LanguageRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `LanguageRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for currency store api route
    * Added `CachedCurrencyRoute`, which adds a cache around the currency loading of a sales channel 
    * Added `CurrencyRouteCacheKeyEvent`, which is triggered when the cache key for the route is generated
    * Added `CurrencyRouteCacheTagsEvent`, which is triggered when the cache item is written, and the attached tags are determined
* Added cache layer for system config loading
    * Added `AbstractSystemConfigLoader`, which is the base class for the `SystemConfigLoader` service
    * Added `CachedSystemConfigLoader`, adds a cache around the system config loading
    * Added `SystemConfigChangedEvent`, which is triggered when a system config value is changed. This event is used to invalidate the cache 
    * Added `SystemConfigLoader`, which is responsible to load the system configuration for a provided sales channel id. 
* Added cache layer for seo url resoling
    * Added `AbstractSeoResolver` which is the base class for the seo url resolving
    * Added `CachedSeoResolver` which adds a cache around the seo url resolving
    * Added `EmptyPathInfoResolver` which handles empty path infos inside before the real seo url resolver is triggered
    * Added `SeoUrlUpdateEvent` which is triggered when a seo url changed
* Added cache layer for sales channel creation
    * Added `AbstractSalesChannelContextFactory` which is the base class for the context factory
    * Added `CachedSalesChannelContextFactory` which adds a cache around the context factory 
* Added cache layer for theme config loading
    * Added `AbstractResolvedConfigLoader` which is the service base class to load theme configurations for a sales channel
    * Added `CachedResolvedConfigLoader` which caches the access to a sales channel theme config
    * Added `CachedResolvedConfigLoaderInvalidator` which invalidates the cache for the theme config cache layer
    * Added `ResolvedConfigLoader` which loads the inherited configuration of a theme for the provided sales channel
    * Added `TemplateConfigAccessor` which controls the access to theme config values
    * Added generic `ThemeConfigChangedEvent` which is triggered each time a theme config is changed
    * Added generic `ThemeConfigValueAccessor` which is used to get theme config values and internally used to trace the access and use the accessed keys as cache tags
* Added cache tracer services which allows tracing config access while a service is called
    * Added `AbstractCacheTracer` which is the base class for the cache tracer
    * Added `AbstractTranslator` which is a new base class for the translator component. Additionally, it allows to trace the accessed snippets inside a request
    * Added `CacheTracer` which is a composition of different traces
* Added new `CacheInvalidator` which is used to invalidate the cache or to log the invalidation for a delayed invalidation.
    * Added `InvalidateCacheEvent` which is triggered when the cache will be invalidated
    * Added `InvalidateCacheTask` which triggers the logger to invalidate the delayed cache invalidation
* Added `shopware.cache` configuration in `shopware.yaml` file for cache configuration
```yaml
    cache:
        invalidation:
            delay: 0
            http_cache: ['logged-in', 'cart-filled']
            product_listing_route: []
            product_detail_route: []
            product_search_route: [ ]
            product_suggest_route: [ ]
            payment_method_route: []
            shipping_method_route: []
            navigation_route: []
            language_route: []
            currency_route: []
```
* Added `CacheStateSubscriber` which is an adaption from the http cache states. The states are applied to the context and can used to pass cache layers
* Added generic `StoreApiRouteCacheKeyEvent` which is used as base class for the different route cache key events
* Added generic `StoreApiRouteCacheTagsEvent` which is used as base class for the different route cache tag events
* Added `CacheCompressor` which is responsible to compress and uncompress the cache items before the cache is written or read
* Added generic `CartChangedEvent` which is triggered for each cart change
* Added `ConfigExtension` which provides to `config` and `theme_config` function
    * Added `config` twig function which allows to get access to system config values
    * Added `theme_config` twig function which allows to get access to theme config values
* Added `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory::$domainId`
* Added `SalesChannelContextServiceParameters` which is used as context service parameter to prevent signature changes in the future
* Added cache for `SwSanitizeTwigFilter`
* Removed `\Shopware\Core\Framework\Adapter\Cache\CacheClearer::invalidateIds`, use `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidator::invalidate` instead
* Removed `\Shopware\Core\Framework\Adapter\Cache\CacheClearer::invalidateTags`, use `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidator::invalidate` instead
* Removed `BlacklistRuleField`, because it leads to performance problems
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityAggregator`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityReader`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher`
* Removed `\Shopware\Core\Checkout\Cart\CartRuleLoader::CHECKOUT_RULE_LOADER_CACHE_KEY`, use `\Shopware\Core\Checkout\Cart\CachedRuleLoader::CACHE_KEY` instead
* Removed `\Shopware\Core\Framework\Context::getUseCache`
* Removed `\Shopware\Core\Framework\Context::disableCache`
* Removed `\Shopware\Core\Framework\Context::$useCache`
* Removed `\Shopware\Core\Framework\Context::enableCache`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getEntityContextCacheKey`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getReadCriteriaCacheKey`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getSearchCacheKey`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getAggregationCacheKey`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getEntityTag`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getSearchTags`
* Removed `\Shopware\Storefront\Framework\Cache\ObjectCacheKeyFinder`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getAssociatedTags`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getFieldTag`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getContextHash`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isBlacklistAware`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isWhitelistAware`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Cache\InvalidateCacheSubscriber`, cache invalidation is now handled for each route individually
* Removed `\Shopware\Core\Framework\Adapter\Cache\CacheClearer::invalidateIds` calls in indexer classes
* Removed `\Shopware\Storefront\Theme\ThemeService::getResolvedThemeConfiguration`, use `theme_config` in templates instead
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField`
* Changed `\Shopware\Core\System\SystemConfig\SystemConfigService::getDomain` annotation. The function is marked as `@interal` and should not be used inside a storefront request
* Changed `\Shopware\Core\System\SystemConfig\SystemConfigService::all` annotation. The function is marked as `@interal` and should not be used inside a storefront request
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\VersionManager::writeAuditLog`, the function checks now `context->hasState(self::DISABLE_AUDIT_LOG)` instead of an extension
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult::__construct`, `page` and `limit` removed and `$entity` added as first parameter
* Changed signature of `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get`, the function expects now the `SalesChannelContextServiceParameters` object as parameter instead of single values

___
# Storefront
* Removed `shopware.config` variable, use `config('my_config_key')` instead
* Removed `shopware.theme` variable, use `theme_config('my_config_key')` instead
* Removed `shopware.theme.breakpoint`, use specific size variable instead `theme_config('breakpoint.sm')`
___
# Upgrade Information
## Twig system config access
The `shopware.config` variable was removed. To access a system config value inside twig, use `config('my_config_key')`.

## Twig theme config access
The `shopware.theme` variable was removed. To access the theme config value inside twig, use `theme_config('my_config_key')`.

## Theme breakpoint config array
The `shopware.theme.breakpoint` config value is no more available, please use the corresponding sizes. If you need to restore the array, you can use the following code:
```
{% set breakpoint = {
    'xs': theme_config('breakpoint.sm'),
    'sm': theme_config('breakpoint.md'),
    'md': theme_config('breakpoint.lg'),
    'lg': theme_config('breakpoint.xl')
} %}
```
