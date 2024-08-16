<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class CacheResponseSubscriber implements EventSubscriberInterface
{
    final public const STATE_LOGGED_IN = CacheStateSubscriber::STATE_LOGGED_IN;
    final public const STATE_CART_FILLED = CacheStateSubscriber::STATE_CART_FILLED;

    final public const CURRENCY_COOKIE = 'sw-currency';
    final public const CONTEXT_CACHE_COOKIE = HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE;

    final public const SYSTEM_STATE_COOKIE = 'sw-states';
    /**
     * @deprecated tag:v6.7.0 - Will be removed
     */
    final public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';

    private const CORE_HTTP_CACHED_ROUTES = [
        'api.acl.privileges.get',
    ];

    /**
     * @param array<string> $cookies
     *
     * @internal
     */
    public function __construct(
        private readonly array $cookies,
        private readonly CartService $cartService,
        private readonly int $defaultTtl,
        private readonly bool $httpCacheEnabled,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        private readonly RequestStack $requestStack,
        private readonly ?string $staleWhileRevalidate,
        private readonly ?string $staleIfError,
        private readonly EventDispatcherInterface $dispatcher
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
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerLogoutEvent::class => 'onCustomerLogout',
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

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            // 404 pages should not be cached by reverse proxy, as the cache hit rate would be super low,
            // and there is no way to invalidate once the url becomes available
            // To still be able to serve 404 pages fast, we don't load the full context and cache the rendered html on application side
            // as we don't have the full context the state handling is broken as no customer or cart is available, even if the customer is logged in
            // @see \Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber::onError
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
            $newValue = $this->buildCacheHash($request, $context);

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

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $event->getSalesChannelContext());
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $context = clone $event->getSalesChannelContext();
        $context->assign(['customer' => null]);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }

    private function buildCacheHash(Request $request, SalesChannelContext $context): string
    {
        if (Feature::isActive('cache_rework')) {
            $parts = [
                HttpCacheCookieEvent::RULE_IDS => $context->getRuleIds(),
                HttpCacheCookieEvent::VERSION_ID => $context->getVersionId(),
                HttpCacheCookieEvent::CURRENCY_ID => $context->getCurrencyId(),
                HttpCacheCookieEvent::TAX_STATE => $context->getTaxState(),
                HttpCacheCookieEvent::LOGGED_IN_STATE => $context->getCustomer() ? 'logged-in' : 'not-logged-in',
            ];

            foreach ($this->cookies as $cookie) {
                if (!$request->cookies->has($cookie)) {
                    continue;
                }

                $parts[$cookie] = $request->cookies->get($cookie);
            }

            $event = new HttpCacheCookieEvent($request, $context, $parts);
            $this->dispatcher->dispatch($event);

            return md5(json_encode($event->getParts(), \JSON_THROW_ON_ERROR));
        }

        return md5(json_encode([
            $context->getRuleIds(),
            $context->getContext()->getVersionId(),
            $context->getCurrency()->getId(),
            $context->getTaxState(),
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
