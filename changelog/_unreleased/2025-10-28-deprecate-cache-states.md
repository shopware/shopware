---
title: Deprecate cache states
issue: #13139
---
# Core
* Added `isCacheable` prop to `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent` and `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent` to allow listener to disable caching.
* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber::setResponseCache()` to not update and add system states header anymore if 6.8 feature flag is activated
* Deprecated `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator` and `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber` as they won't be needed anymore with the removal of the cache states.
* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStore` to not write or look up the response when the cache key disables caching. Additionally it won't validate cache states anymore when 6.8 feature flag is enabled.
* Deprecated following constants:
  * `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE`
  * `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER`
  * `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_LOGGED_IN`
  * `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_CART_FILLED`
* Deprecated following configuration:
  * `shopware.cache.invalidation.http_cache`
___
# Next Major Version Changes

## Deprecation of `sw-states` handling and new way to disable caching
The `sw-states` handling is deprecated, which means by default the HTTP-Cache will also be active for logged in customers or when the cart is filled in the next major version.
Due to the rework of the contained rules in the cache hash, this becomes efficiently possible.

You should rework you extensions to also work with enabled cache for logged in customers and when the cart is filled.
If your extension is too dynamic you can restore the old behaviour by manually creating a cache key listener in your plugin:
```php
class HttpCacheKeyListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheCookieEvent::class => 'onCacheCookie',
        ];
    }

    public function onCacheCookie(HttpCacheCookieEvent $event): void
    {
        // disable cache for logged in customers
        if ($event->context->getCustomer() !== null) {
            $event->isCacheable = false;
        }

        // disable cache for filled carts
        $cart = $this->cartService->getCart($event->context->getToken(), $event->context);
        if ($cart->getLineItems()->count() > 0) {
            $event->isCacheable = false;
        }
    }
}
```
**Note:** Keep in mind that this has severe performance implications and should only be used if absolutely necessary.

For this the following classes and constants were deprecated:
* `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_LOGGED_IN`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_CART_FILLED`