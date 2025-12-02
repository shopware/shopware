<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
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
 */
#[Package('framework')]
class CacheResponseSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly int $defaultTtl,
        private readonly bool $httpCacheEnabled,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        private readonly ?string $staleWhileRevalidate,
        private readonly ?string $staleIfError,
        private readonly CacheHashService $cacheHashService,
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
        if (!$this->httpCacheEnabled) {
            return;
        }

        $response = $event->getResponse();

        $request = $event->getRequest();

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof SalesChannelContext) {
            return;
        }

        if (!$this->maintenanceResolver->shouldBeCached($request)) {
            return;
        }

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            // 404 pages should not be cached by reverse proxy, as the cache hit rate would be super low,
            // and there is no way to invalidate once the url becomes available
            // To still be able to serve 404 pages fast, we don't load the full context and cache the rendered html on application side
            // as we don't have the full context the state handling is broken as no customer or cart is available, even if the customer is logged in
            // @see \Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber::onError
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
        $this->cacheHashService->applyCacheHash($request, $context, $cart, $response);

        if (!$request->isMethod(Request::METHOD_GET)
        ) {
            return;
        }

        /** @var bool|array{maxAge?: int, states?: list<string>}|null $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        if ($cache === true) {
            $cache = [];
        }

        /** @deprecated tag:v6.8.0 - can be removed when cache states are always empty */
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            if ($this->hasInvalidationState($cache['states'] ?? [], $states)) {
                return;
            }
        }

        $maxAge = $cache['maxAge'] ?? $this->defaultTtl;

        $response->setSharedMaxAge($maxAge);
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            $response->headers->set(
                HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER,
                implode(',', $cache['states'] ?? [])
            );
        }

        if ($this->staleIfError !== null) {
            $response->headers->addCacheControlDirective('stale-if-error', $this->staleIfError);
        }

        if ($this->staleWhileRevalidate !== null) {
            $response->headers->addCacheControlDirective('stale-while-revalidate', $this->staleWhileRevalidate);
        }
    }

    public function setResponseCacheHeader(ResponseEvent $event): void
    {
        if (!$this->httpCacheEnabled) {
            return;
        }

        $response = $event->getResponse();

        $request = $event->getRequest();

        /** @var bool|array{maxAge?: int, states?: list<string>}|null $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
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

        if (empty($states)) {
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
}
