<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-import-type CacheAttributeArray from \Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute
 * @phpstan-import-type CacheAttributeType from \Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute
 */
#[Package('framework')]
class CacheResponseSubscriber implements EventSubscriberInterface
{
    private const POLICY_AREA_STOREFRONT = 'storefront';
    private const POLICY_AREA_STORE_API = 'store_api';

    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        /**
         * @deprecated tag:v6.8.0 - Will be removed, use cache policies instead
         */
        private readonly int $defaultTtl,
        private readonly bool $httpCacheEnabled,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        /**
         * @deprecated tag:v6.8.0 - Will be removed, use cache policies instead
         */
        private readonly ?string $staleWhileRevalidate,
        /**
         * @deprecated tag:v6.8.0 - Will be removed, use cache policies instead
         */
        private readonly ?string $staleIfError,
        private readonly CacheHeadersService $cacheHeadersService,
        private readonly CachePolicyProvider $policyProvider,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
        ];
    }

    public function setResponseCache(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        $area = $this->isStoreApi($request) ? self::POLICY_AREA_STORE_API : self::POLICY_AREA_STOREFRONT;

        if (!$this->httpCacheEnabled) {
            // no-store attribute still has to be processed even in early return case
            if ($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE)) {
                $this->applyPolicy($request, $response, $area, false, null);
            }

            return;
        }

        if (!$this->maintenanceResolver->shouldBeCached($request)) {
            $this->noCache($request, $response, $area);

            return;
        }

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            // 404 pages should not be cached by reverse proxy, as the cache hit rate would be super low,
            // and there is no way to invalidate once the url becomes available
            // To still be able to serve 404 pages fast, we don't load the full context and cache the rendered html on application side
            // as we don't have the full context the state handling is broken as no customer or cart is available, even if the customer is logged in
            // @see \Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber::onError
            $this->noCache($request, $response, $area);

            return;
        }

        // Normalize attribute value to CacheAttribute instance or null
        /** @var CacheAttributeType $cacheAttributeValue */
        $cacheAttributeValue = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        $cacheAttribute = CacheAttribute::fromAttributeValue($cacheAttributeValue);

        // Preventing applying cache headers to the routes that are marked for caching, but feature flag is disabled
        if ($area === self::POLICY_AREA_STORE_API && !Feature::isActive('CACHE_REWORK') && !Feature::isActive('v6.8.0.0')) {
            $this->noCache($request, $response, $area);

            return;
        }

        $route = $request->attributes->get('_route');
        /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12968) */
        if ($route === 'frontend.checkout.configure') {
            if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
                $this->setCurrencyCookie($request, $response);
            }
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        /** @deprecated tag:v6.8.0 - states can be removed */
        if (Feature::isActive('v6.8.0.0') || Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            $states = [];
        } else {
            $states = $this->updateSystemState($cart, $context, $request, $response);
        }

        // The cache hash reflects the internal state of the context to properly cache pages
        // when multiple permutations exist (e.g. different currencies etc)
        // therefore, it needs to be applied to every request (including POST), especially when POST-requests mutate the context,
        // even when the response is not cached itself, so that the cache-hash on the client is updated for the next request
        //
        // It should be called here as side effects (cookie, header) should also appy for non-cacheable responses
        $cacheHashEvent = $this->cacheHeadersService->applyCacheHash($request, $context, $cart, $response);

        if (!$request->isMethod(Request::METHOD_GET)) {
            $this->noCache($request, $response, $area);

            return;
        }

        if ($cacheAttribute === null) {
            $this->noCache($request, $response, $area);

            return;
        }

        // No cache when dynamic calculation says so
        if ($cacheHashEvent && !$cacheHashEvent->shouldResponseBeCached()) {
            // Response is not cacheable because of dynamic calculation, giving a hint to the reverse proxy
            $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
            $this->noCache($request, $response, $area);

            return;
        }

        $cacheHash = $cacheHashEvent?->getHash();
        // No cache when client cache hash does not match the expected one. This protects from cache poisoning
        if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
            $clientHash = $request->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE) ??
                $request->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, '');
            $expectedHash = $cacheHash ?? '';

            if ($clientHash !== $expectedHash) {
                $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
                $this->noCache($request, $response, $area);

                return;
            }
        }

        /** @deprecated tag:v6.8.0 - can be removed when cache states are always empty */
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            if ($this->hasInvalidationState($cacheAttribute->states ?? [], $states)) {
                $this->noCache($request, $response, $area);

                return;
            }
        }

        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            $response->headers->set(
                HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER,
                implode(',', $cacheAttribute->states ?? [])
            );
        }

        // old behavior
        if (!Feature::isActive('CACHE_REWORK') && !Feature::isActive('v6.8.0.0')) {
            $sMaxAge = $cacheAttribute->sMaxAge ?? $this->defaultTtl;
            $response->setSharedMaxAge($sMaxAge);

            if ($this->staleIfError !== null) {
                $response->headers->addCacheControlDirective('stale-if-error', $this->staleIfError);
            }

            if ($this->staleWhileRevalidate !== null) {
                $response->headers->addCacheControlDirective('stale-while-revalidate', $this->staleWhileRevalidate);
            }

            return;
        }

        $this->applyPolicy($request, $response, $area, true, $cacheAttribute);
    }

    public function setResponseCacheHeader(ResponseEvent $event): void
    {
        if (!$this->httpCacheEnabled) {
            return;
        }

        $response = $event->getResponse();

        $request = $event->getRequest();

        /** @var CacheAttributeType $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
    }

    private function noCache(Request $request, Response $response, string $area): void
    {
        if (!Feature::isActive('CACHE_REWORK') && !Feature::isActive('v6.8.0.0')) {
            // do nothing for backwards compatibility
            return;
        }
        $this->applyPolicy($request, $response, $area, false, null);
    }

    private function applyPolicy(Request $request, Response $response, string $area, bool $cacheable, ?CacheAttribute $cacheAttribute): void
    {
        $route = (string) $request->attributes->get('_route', '');
        $enforceNoStore = $request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE);

        $policy = $this->policyProvider->getPolicy($route, $area, $cacheable, $cacheAttribute, $enforceNoStore);

        // reset existing cache-control to avoid mixing policies
        $response->headers->remove('cache-control');

        // apply resolved policy to response
        $response->setCache($policy->cacheControl->toArray());
    }

    /**
     * @param list<string> $cacheStates
     * @param list<string> $states
     */
    private function hasInvalidationState(array $cacheStates, array $states): bool
    {
        foreach ($states as $state) {
            if (\in_array($state, $cacheStates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * System states can be used to stop caching routes at certain states. For example,
     * the checkout routes are no longer cached if the customer has products in the cart or is logged in.
     *
     * @return list<string>
     */
    private function updateSystemState(Cart $cart, SalesChannelContext $context, Request $request, Response $response): array
    {
        $states = $this->getSystemStates($request, $context, $cart);

        if ($states === []) {
            if ($request->cookies->has(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE)) {
                $response->headers->removeCookie(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);
                $response->headers->clearCookie(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);
            }

            return [];
        }

        $newStates = implode(',', $states);

        if ($request->cookies->get(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE) !== $newStates) {
            $cookie = Cookie::create(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, $newStates);
            $cookie->setSecureDefault($request->isSecure());

            $response->headers->setCookie($cookie);
        }

        return $states;
    }

    /**
     * @return list<string>
     */
    private function getSystemStates(Request $request, SalesChannelContext $context, Cart $cart): array
    {
        $states = [];
        $swStates = (string) $request->cookies->get(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);
        if ($swStates !== '') {
            $states = array_flip(explode(',', $swStates));
        }

        $states = $this->switchState($states, CacheStateSubscriber::STATE_LOGGED_IN, $context->getCustomer() !== null);

        $states = $this->switchState($states, CacheStateSubscriber::STATE_CART_FILLED, $cart->getLineItems()->count() > 0);

        return array_keys($states);
    }

    /**
     * @param array<string, int|bool> $states
     *
     * @return array<string, int|bool>
     */
    private function switchState(array $states, string $key, bool $match): array
    {
        if ($match) {
            $states[$key] = true;

            return $states;
        }

        unset($states[$key]);

        return $states;
    }

    /**
     * @deprecated tag:v6.8.0 - can be removed when currency cookie is removed
     */
    private function setCurrencyCookie(Request $request, Response $response): void
    {
        $currencyId = $request->get(SalesChannelContextService::CURRENCY_ID);

        if (!$currencyId) {
            return;
        }

        $cookie = Cookie::create(HttpCacheKeyGenerator::CURRENCY_COOKIE, $currencyId);
        $cookie->setSecureDefault($request->isSecure());

        $response->headers->setCookie($cookie);
    }

    private function isStoreApi(Request $request): bool
    {
        return \in_array(
            StoreApiRouteScope::ID,
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        );
    }
}
