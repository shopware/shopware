---
title: Cache rework
issue: NEXT-31669
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---
# Core
*
___
# API
*
___
# Administration
*
___
# Storefront
*
___

# Upgrade information

## Global rules
* Removed context.ruleIds / salesChannelContext.ruleIds
* Removed `RuleAreaUpdater`
* We Removed the `AbstractRuleLoader`, you can use the `\Shopware\Core\Content\Rule\RuleLoader` instead.

## Caching
* Removed rules from http cache key and context hash
* Removed all Cached*Route.php
* Removed system states + invalidation logic of it.
* Removed `AbstractCacheTracer`
* Removed `CacheStateValidator`
* Removed `CacheDecorator`
* Removed `CacheStateSubscriber`
* Removed `TranslatorCacheInvalidate`
* Removed all `Cached*Route` store api classes
* Refactored `CacheInvalidationSubscriber` to reduce invalidation count
* Changed `/suggest`, `/widgets/search`, `/search` route are no more http cached
* Changed `CacheInvalidator::invalidateExpired` return value for invalidated tags
* New `%shopware.http_cache.cookies%` config
* New HttpCacheCookieEvent event
* New `AddCacheTagEvent` event for tagging
* New `InvalidateProductCache` event for product cache invalidation
* New http cache hash generation (customer-group-id, tax-state, currency-id, version-id, cookies, config + event)
* New `CacheInvalidateDelayedCommand` to invalidate delayed cache
* New `cache:clear:delayed` command

## Templating
* Removed `sw_breadcrumb_full`, use `sw_breadcrumb_full_by_id` instead
* Removed global `page.salesChannelShippingMethods` and `page.salesChannelPaymentMethods`
* New global data:
    * `language` - `{id: string, name: string, locale: string}`
    * `navigationId` - active navigation id
    * `navigationPath` - path of the active navigation
    * `minSearchLength` - minimal search length
* Changed access to active currency from `page.header.activeCurrency` to global `context.currency` variable
* Changed access to active language from `page.header.activeLanguage` to global `language` variable
* Changed access to active navigation from `page.header.navigation.active.id` to global `navigationId` variable
* Changed access to active navigation path from `page.header.navigation.active.path` to global `navigationPath` variable
* Changed access to min search length in `header/search.html.twig` from `page.header.activeLanguage.productSearchConfig.minSearchLength` to global `minSearchLength`

### Delayed cache
* Removed `shopware.cache.invalidation.delay` config, delayed cache is now activated by default
* New `cache.api.service`::`delayed` function
* New `/api/_action/cache-delayed` route

## Cart
* Changed `AbstractCartPersister::load` always returns a cart
* Removed `CartRuleLoader`, use `CartService` or `CartPersister + CartCalculator`
* New `Cart::ruleIds` available re-producible for each processor
* New `Cart::new` flag
* New `CountryTaxCalculator::calculate`
* New `CartCalculatedEvent`

## Indexing
* Changed `CategoryIndexer::index` behavior, to no more index all sub categories of provided ids

## Navigation
* Removed `NavigationRoute::load` method
* Removed global `page.header` and `page.footer`
* Removed `header.serviceMenu` variable
* Changed navigation handling, navigation has no more active state, this is handled via CSS in `layout/navigation/active-styling.html.twig`
* New `\Shopware\Core\Content\Media\Core\Dto\Media` object
* New `/esi/header` and `/esi/footer` routes
* New `header`, `footer`, `service` route in navigation route
* New `\Shopware\Core\Content\Category\Dto\Navigation` + `\Shopware\Core\Content\Category\Dto\NavigationItem` object
* New `header.service` variable
* New `paymentMethods` and `shippingMethods` to `FooterPagelet`
* Changed loading of header template, header is now loaded via `/esi/header` route, by using `{{ render_esi(url('frontend.esi.header')) }}` twig function
* Changed loading of footer template, header is now loaded via `/esi/footer` route, by using `{{ render_esi(url('frontend.esi.footer')) }}` twig function
* Changed `navigationTree` in `navigation/categories.html.twig`, which contains now `NavigationItem` instead of `TreeItem`
* Changed `children` in `navigation/offcanvas/categories.html.twig`, which contains now `NavigationItem` instead of `TreeItem`
* Changed `navigation` and `serviceMenu` in `layout/footer/footer.html.twig`, which contains now `Navigation` instead of `Tree`
* Changed `item` in `layout/navigation/offcanvas/active-item-link.html.twig`, which contains now `NavigationItem` instead of `TreeItem`

## Product pricing
* Removed `ProductPrice` entity, definition and collection
* Removed `ProductPriceCalculator::calculate`
* New `product_pricing` entity
* New `matrix`, `listing`, `quantity` in `ProductPriceCalculator`

## admin improvements
* New `repository-loader.data.js`
* New `row-classes` event in `sw-data-grid`
* New `actions-column` slot in `sw-data-grid`
* New `sw-products-pricing-service.js` for product pricing data handling


# Upgrade Information

```php
<?php

namespace Examples;

class GetCollectedTags {
    public function __construct(private readonly CacheTagCollector $collector) {}
    
    public function afterRequest(Request $request) 
    {
        $tags = $this->collector->get($request);
        
        // contains all collected cache tags during the request
    }
} 
```

```php
<?php

namespace Examples;

class AbstractMyRoute {
    abstract public function load();
}

class MyRoute extends AbstractMyRoute {
    #[Route(path: '/', name: 'foo', defaults: ['_httpCache' => true], methods: ['GET'])]
    public function load() 
    {
        // 6.7.x - routes are now http cached
        $this->dispatcher->dispatch(new AddCacheTagEvent('my-tag-1', 'my-tag-2'));
        
        return new Response();
    }
}

class 6_6_x_CachedRoute extends AbstractMyRoute { 
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly AbstractMyRoute $decorated
    ) {}
    
    public function load() 
    {
        // 6.6.x - should be removed
        return $this->cache->get($key, function (ItemInterface $item)  {
            return $this->decorated->load();
        });
    }
}
```


```php
<?php

namespace Examples;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Symfony\Component\HttpFoundation\Request;

class NavigationRouteChange {
    public function __construct(private readonly NavigationRoute $route) {}
    
    public function foo(SalesChannelContext $context) 
    {
        // 6.6.x
        $navigation = $this->route->load(
            activeId: $activeId,
            rootId: $context->getSalesChannel()->getNavigationCategoryId(),
            request: new Request(),
            context: $context
        );
         
        $service = $this->route->load(
            activeId: $activeId,
            rootId: $context->getSalesChannel()->getServiceCategoryId(),
            request: new Request(),
            context: $context
        );

        $footer = $this->route->load(
            activeId: $activeId,
            rootId: $context->getSalesChannel()->getFooterCategoryId(),
            request: new Request(),
            context: $context
        ); 
        
        assert($navigation->getCategories() instanceof CategoryCollection);
        assert($service->getCategories() instanceof CategoryCollection);
        assert($footer->getCategories() instanceof CategoryCollection);

        // 6.7.x
        $navigation = $this->route->header(
            request: new Request(),
            context: $context
        )->getObject();
        
        $footer = $this->route->footer(
            request: new Request(),
            context: $context
        )->getObject();
        
        $service = $this->route->service(
            request: new Request(),
            context: $context
        )->getObject();
        
        assert($navigation instanceof CategoryCollection);
        assert($service instanceof CategoryCollection);
        assert($footer instanceof CategoryCollection);
    }
}
```


```js
export default {
    inject: ['cacheApiService'],
    
    methods: {
        clearCache() {0
            // 6.6.x - invalidates all caches immediately
            this.cacheApiService.clear();
            
            // 6.7.x - only invalidates caches which are expired or tagged as invalid by data changes
            this.cacheApiService.delayed();
        }
    }
}
```

## Cache key generation
The http cache key is now build up by the following values:
- request: `request.uri`
- config: `%kernel.cache.hash%`
- cookie: `sw-cache-hash`
    - if this cookie is not set, we check for `sw-currency`

The `sw-cache-hash` is only calculated when the customer logs in. Not anymore when he adds something to the cart.
The `sw-cache-hash` is build up by the following values:

- context: `customer-group-id`
- context: `rule-ids`
- context: `tax-state`
- context: `currency-id`
- context: `version-id`
- config: `%shopware.http_cache.cookies%`
- event: `HttpCacheCookieEvent`

We added a new config, which allows you to easily extend the sw-cache-hash, by setting a cookie and add the cookie name to the config. The value will be added to the hash and afterwards to the cache key.
```yaml
shopware:
  http_cache:
    cookies:
      - my_custom_cookie
      - my_other_cookie
```

Or if you prefer a direct manipulation of the cache-hash, you can use the event `HttpCacheCookieEvent`:
```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ManipulateCacheHashExample
{
    public function __invoke(HttpCacheCookieEvent $event)
    {
        $event->add('my-key', 'my-value');
        $event->remove('customer-group-id');
    }
}
```



