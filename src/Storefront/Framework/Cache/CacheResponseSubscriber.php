<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('storefront')]
class CacheResponseSubscriber implements EventSubscriberInterface
{
    final public const STATE_LOGGED_IN = CacheStateSubscriber::STATE_LOGGED_IN;
    final public const STATE_CART_FILLED = CacheStateSubscriber::STATE_CART_FILLED;

    final public const CURRENCY_COOKIE = 'sw-currency';
    final public const CONTEXT_CACHE_COOKIE = 'sw-cache-hash';
    final public const SYSTEM_STATE_COOKIE = 'sw-states';
    final public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';

    private const CORE_HTTP_CACHED_ROUTES = [
        'api.acl.privileges.get',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly int $defaultTtl,
        private readonly bool $httpCacheEnabled,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        private readonly bool $reverseProxyEnabled,
        private readonly ?string $staleWhileRevalidate,
        private readonly ?string $staleIfError
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'addHttpCacheToCoreRoutes',
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
            BeforeSendResponseEvent::class => 'updateCacheControlForBrowser',
        ];
    }

    public function addHttpCacheToCoreRoutes(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (\in_array($route, self::CORE_HTTP_CACHED_ROUTES, true)) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        }
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

        $route = $request->attributes->get('_route');
        if ($route === 'frontend.checkout.configure') {
            $this->setCurrencyCookie($request, $response);
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $states = $this->updateSystemState($cart, $context, $request, $response);

        // We need to allow it on login, otherwise the state is wrong
        if (!($route === 'frontend.account.login' || $request->getMethod() === Request::METHOD_GET)) {
            return;
        }

        if ($context->getCustomer() || $cart->getLineItems()->count() > 0) {
            $newValue = $this->buildCacheHash($context);

            if ($request->cookies->get(self::CONTEXT_CACHE_COOKIE, '') !== $newValue) {
                $cookie = Cookie::create(self::CONTEXT_CACHE_COOKIE, $newValue);
                $cookie->setSecureDefault($request->isSecure());

                $response->headers->setCookie($cookie);
            }
        } elseif ($request->cookies->has(self::CONTEXT_CACHE_COOKIE)) {
            $response->headers->removeCookie(self::CONTEXT_CACHE_COOKIE);
            $response->headers->clearCookie(self::CONTEXT_CACHE_COOKIE);
        }

        /** @var bool|array{maxAge?: int, states?: list<string>}|null $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        if ($cache === true) {
            $cache = [];
        }

        if ($this->hasInvalidationState($cache['states'] ?? [], $states)) {
            return;
        }

        $maxAge = $cache['maxAge'] ?? $this->defaultTtl;

        $response->setSharedMaxAge($maxAge);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set(
            self::INVALIDATION_STATES_HEADER,
            implode(',', $cache['states'] ?? [])
        );

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
     * In the default HttpCache implementation the reverse proxy cache is implemented too in PHP and triggered before the response is send to the client. We don't need to send the "real" cache-control headers to the end client (browser/cloudflare).
     * If a external reverse proxy cache is used we still need to provide the actual cache-control, so the external system can cache the system correctly and set the cache-control again to
     */
    public function updateCacheControlForBrowser(BeforeSendResponseEvent $event): void
    {
        if ($this->reverseProxyEnabled) {
            return;
        }

        $response = $event->getResponse();

        $noStore = $response->headers->getCacheControlDirective('no-store');

        // We don't want that the client will cache the website, if no reverse proxy is configured
        $response->headers->remove('cache-control');
        $response->setPrivate();

        if ($noStore) {
            $response->headers->addCacheControlDirective('no-store');
        } else {
            $response->headers->addCacheControlDirective('no-cache');
        }
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

    private function buildCacheHash(SalesChannelContext $context): string
    {
        return md5(json_encode([
            $context->getRuleIds(),
            $context->getContext()->getVersionId(),
            $context->getCurrency()->getId(),
            $context->getCustomer() ? 'logged-in' : 'not-logged-in',
        ], \JSON_THROW_ON_ERROR));
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
            if ($request->cookies->has(self::SYSTEM_STATE_COOKIE)) {
                $response->headers->removeCookie(self::SYSTEM_STATE_COOKIE);
                $response->headers->clearCookie(self::SYSTEM_STATE_COOKIE);
            }

            return [];
        }

        $newStates = implode(',', $states);

        if ($request->cookies->get(self::SYSTEM_STATE_COOKIE) !== $newStates) {
            $cookie = Cookie::create(self::SYSTEM_STATE_COOKIE, $newStates);
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
        $swStates = (string) $request->cookies->get(self::SYSTEM_STATE_COOKIE);
        if ($swStates !== '') {
            $states = array_flip(explode(',', $swStates));
        }

        $states = $this->switchState($states, self::STATE_LOGGED_IN, $context->getCustomer() !== null);

        $states = $this->switchState($states, self::STATE_CART_FILLED, $cart->getLineItems()->count() > 0);

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

    private function setCurrencyCookie(Request $request, Response $response): void
    {
        $currencyId = $request->get(SalesChannelContextService::CURRENCY_ID);

        if (!$currencyId) {
            return;
        }

        $cookie = Cookie::create(self::CURRENCY_COOKIE, $currencyId);
        $cookie->setSecureDefault($request->isSecure());

        $response->headers->setCookie($cookie);
    }
}
