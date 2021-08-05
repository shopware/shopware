<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheResponseSubscriber implements EventSubscriberInterface
{
    public const STATE_LOGGED_IN = CacheStateSubscriber::STATE_LOGGED_IN;
    public const STATE_CART_FILLED = CacheStateSubscriber::STATE_CART_FILLED;

    public const CURRENCY_COOKIE = 'sw-currency';
    public const CONTEXT_CACHE_COOKIE = 'sw-cache-hash';
    public const SYSTEM_STATE_COOKIE = 'sw-states';
    public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';

    private CartService $cartService;

    private int $defaultTtl;

    private bool $httpCacheEnabled;

    private MaintenanceModeResolver $maintenanceResolver;

    public function __construct(
        CartService $cartService,
        int $defaultTtl,
        bool $httpCacheEnabled,
        MaintenanceModeResolver $maintenanceModeResolver
    ) {
        $this->cartService = $cartService;
        $this->defaultTtl = $defaultTtl;
        $this->httpCacheEnabled = $httpCacheEnabled;
        $this->maintenanceResolver = $maintenanceModeResolver;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
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

        if ($this->maintenanceResolver->isMaintenanceRequest($request)) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $route = $request->attributes->get('_route');
        if ($route === 'frontend.checkout.configure') {
            $this->setCurrencyCookie($request, $response);
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $states = $this->updateSystemState($cart, $context, $request, $response);

        if ($request->getMethod() !== Request::METHOD_GET) {
            return;
        }

        if ($context->getCustomer() || $cart->getLineItems()->count() > 0) {
            $cookie = Cookie::create(self::CONTEXT_CACHE_COOKIE, $this->buildCacheHash($context));
            $cookie->setSecureDefault($request->isSecure());

            $response->headers->setCookie($cookie);
        } else {
            $response->headers->removeCookie(self::CONTEXT_CACHE_COOKIE);
            $response->headers->clearCookie(self::CONTEXT_CACHE_COOKIE);
        }

        $config = $request->attributes->get('_' . HttpCache::ALIAS);
        if (empty($config)) {
            return;
        }

        /** @var HttpCache $cache */
        $cache = array_shift($config);

        if ($this->hasInvalidationState($cache, $states)) {
            return;
        }

        $maxAge = $cache->getMaxAge() ?? $this->defaultTtl;

        $response->setSharedMaxAge($maxAge);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set(
            self::INVALIDATION_STATES_HEADER,
            implode(',', $cache->getStates())
        );
    }

    private function hasInvalidationState(HttpCache $cache, array $states): bool
    {
        foreach ($states as $state) {
            if (\in_array($state, $cache->getStates(), true)) {
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
        ]));
    }

    /**
     * System states can be used to stop caching routes at certain states. For example,
     * the checkout routes are no longer cached if the customer has products in the cart or is logged in.
     */
    private function updateSystemState(Cart $cart, SalesChannelContext $context, Request $request, Response $response): array
    {
        $states = $this->getSystemStates($request, $context, $cart);

        if (empty($states)) {
            $response->headers->removeCookie(self::SYSTEM_STATE_COOKIE);
            $response->headers->clearCookie(self::SYSTEM_STATE_COOKIE);

            return [];
        }

        $cookie = Cookie::create(self::SYSTEM_STATE_COOKIE, implode(',', $states));
        $cookie->setSecureDefault($request->isSecure());

        $response->headers->setCookie($cookie);

        return $states;
    }

    private function getSystemStates(Request $request, SalesChannelContext $context, Cart $cart): array
    {
        $states = [];
        $swStates = (string) $request->cookies->get(self::SYSTEM_STATE_COOKIE);
        if ($swStates !== null) {
            $states = explode(',', $swStates);
            $states = array_flip($states);
        }

        $states = $this->switchState($states, self::STATE_LOGGED_IN, $context->getCustomer() !== null);

        $states = $this->switchState($states, self::STATE_CART_FILLED, $cart->getLineItems()->count() > 0);

        return array_keys($states);
    }

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
