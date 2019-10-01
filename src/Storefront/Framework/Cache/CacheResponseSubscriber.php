<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheResponseSubscriber implements EventSubscriberInterface
{
    public const STATE_LOGGED_IN = 'logged-in';
    public const STATE_CART_FILLED = 'cart-filled';

    public const CURRENCY_COOKIE = 'sw-currency';
    public const CONTEXT_CACHE_COOKIE = 'sw-cache-hash';
    public const SYSTEM_STATE_COOKIE = 'sw-states';
    public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var int
     */
    private $defaultTtl;

    public function __construct(CartService $cartService, int $defaultTtl)
    {
        $this->cartService = $cartService;
        $this->defaultTtl = $defaultTtl;
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
        $response = $event->getResponse();

        $request = $event->getRequest();

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $route = $request->attributes->get('_route');
        if ($route === 'frontend.checkout.configure') {
            $this->setCurrencyCookie($request, $response);
        }

        /* @var SalesChannelContext $context */
        $states = $this->updateSystemState($context, $request, $response);

        if ($request->getMethod() !== Request::METHOD_GET) {
            return;
        }

        if ($context->getCustomer()) {
            $response->headers->setCookie(Cookie::create(self::CONTEXT_CACHE_COOKIE, $this->buildCacheHash($context)));
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
        $maxAge = $maxAge ?? 3600;

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
            if (in_array($state, $cache->getStates(), true)) {
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
    private function updateSystemState(SalesChannelContext $context, Request $request, Response $response): array
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $states = $this->getSystemStates($request, $context, $cart);

        if (empty($states)) {
            $response->headers->removeCookie(self::SYSTEM_STATE_COOKIE);
            $response->headers->clearCookie(self::SYSTEM_STATE_COOKIE);

            return [];
        }

        $response->headers->setCookie(
            Cookie::create(self::SYSTEM_STATE_COOKIE, implode(',', $states))
        );

        return $states;
    }

    private function getSystemStates(Request $request, SalesChannelContext $context, Cart $cart): array
    {
        $states = [];
        if ($request->cookies->has(self::SYSTEM_STATE_COOKIE)) {
            $states = explode(',', $request->cookies->get(self::SYSTEM_STATE_COOKIE));
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

        $response->headers->setCookie(Cookie::create(self::CURRENCY_COOKIE, $currencyId));
    }
}
