<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\CacheResponseSubscriber
 */
class CacheResponseSubscriberTest extends TestCase
{
    private const IP = '127.0.0.1';

    private static array $hashes = [];

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => 'addHttpCacheToCoreRoutes',
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
            ],
            BeforeSendResponseEvent::class => 'updateCacheControlForBrowser',
        ];

        static::assertSame($expected, CacheResponseSubscriber::getSubscribedEvents());
    }

    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            false,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $customer = $this->createMock(CustomerEntity::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = new Response();
        $expectedHeaders = $response->headers->all();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        static::assertSame($expectedHeaders, $response->headers->all());
    }

    /**
     * @dataProvider cashHashProvider
     */
    public function testGenerateCashHashWithItemsInCart(?CustomerEntity $customer, Cart $cart, bool $hasCookie, ?string $hashName = null): void
    {
        $service = $this->createMock(CartService::class);
        $service->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            $service,
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        if ($hasCookie === false) {
            $request->cookies->set(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        if ($hasCookie) {
            static::assertTrue($response->headers->has('set-cookie'));

            $cookies = array_filter($response->headers->getCookies(), function (Cookie $cookie) {
                return $cookie->getName() === CacheResponseSubscriber::CONTEXT_CACHE_COOKIE;
            });

            static::assertCount(1, $cookies);
            /** @var Cookie $cookie */
            $cookie = array_shift($cookies);

            static::assertNotNull($cookie->getValue());
            if ($hashName) {
                if (!isset(self::$hashes[$hashName])) {
                    self::$hashes[$hashName] = $cookie->getValue();
                }

                foreach (self::$hashes as $name => $value) {
                    if ($hashName === $name) {
                        static::assertEquals(
                            $value,
                            $cookie->getValue(),
                            sprintf('Hashes for state "%s" did not match, got "%s", but expected "%s"', $hashName, $cookie->getValue(), $value)
                        );
                    } else {
                        static::assertNotEquals(
                            $value,
                            $cookie->getValue(),
                            sprintf('Hashes for state "%s" and state "%s" should not match, but did match.', $hashName, $name)
                        );
                    }
                }
            }
        } else {
            $cookies = $response->headers->getCookies();
            static::assertNotEmpty($cookies, 'the client cookie should be cleared');

            foreach ($cookies as $cookie) {
                static::assertSame(1, $cookie->getExpiresTime(), 'cookie should expire');
            }
        }
    }

    /**
     * @dataProvider maintenanceRequest
     */
    public function testMaintenanceRequest(bool $active, array $whitelist, bool $shouldBeCached): void
    {
        $cartService = $this->createMock(CartService::class);
        $requestStack = new RequestStack();

        $subscriber = new CacheResponseSubscriber(
            $cartService,
            100,
            true,
            new MaintenanceModeResolver($requestStack),
            false,
            null,
            null
        );

        $customer = $this->createMock(CustomerEntity::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $active);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, json_encode($whitelist));
        $request->server->set('REMOTE_ADDR', self::IP);
        $requestStack->push($request);

        static::assertSame(self::IP, $request->getClientIp());

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $cart = new Cart('a', 'token');

        $count = $shouldBeCached ? 1 : 0;

        $cartService->expects(static::exactly($count))
            ->method('getCart')
            ->willReturn($cart);

        $subscriber->setResponseCache($event);
    }

    public function cashHashProvider(): iterable
    {
        $emptyCart = new Cart('empty', 'empty');
        $customer = $this->createMock(CustomerEntity::class);

        $filledCart = new Cart('filled', 'filled');
        $filledCart->add(new LineItem('test', 'test', 'test'));

        yield 'Test with no logged in customer' => [null, $emptyCart, false];
        yield 'Test with filled cart' => [null, $filledCart, true, 'not-logged-in'];
        // all logged in customer should share the same cache hash if no rules match
        yield 'Test with logged in customer' => [$customer, $emptyCart, true, 'logged-in'];
        yield 'Test with filled cart and logged in customer' => [$customer, $filledCart, true, 'logged-in'];
    }

    public function maintenanceRequest(): iterable
    {
        yield 'Always cache requests when maintenance is inactive' => [false, [], true];
        yield 'Always cache requests when maintenance is active' => [true, [], true];
        yield 'Do not cache requests of whitelisted ip' => [true, [self::IP], false];
        yield 'Cache requests if ip is not whitelisted' => [true, ['120.0.0.0'], true];
    }

    /**
     * @dataProvider headerCases
     */
    public function testResponseHeaders(bool $reverseProxyEnabled, ?string $beforeHeader, string $afterHeader): void
    {
        $response = new Response();

        if ($beforeHeader) {
            $response->headers->set('cache-control', $beforeHeader);
        }

        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            $reverseProxyEnabled,
            null,
            null
        );

        $subscriber->updateCacheControlForBrowser(new BeforeSendResponseEvent(new Request(), $response));

        static::assertSame($afterHeader, $response->headers->get('cache-control'));
    }

    public function headerCases(): iterable
    {
        yield 'no cache proxy, default response' => [
            false,
            null,
            'no-cache, private',
        ];

        yield 'no cache proxy, default response with no-store (/account)' => [
            false,
            'no-store, private',
            'no-store, private',
        ];

        // @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control#preventing_storing
        yield 'no cache proxy, no-cache will be replaced with no-store' => [
            false,
            'no-store, no-cache, private',
            'no-store, private',
        ];

        yield 'no cache proxy, public content served as private for end client' => [
            false,
            'public, s-maxage=64000',
            'no-cache, private',
        ];

        yield 'cache proxy, cache-control is not touched' => [
            true,
            'public',
            'public',
        ];

        yield 'cache proxy, cache-control is not touched #2' => [
            true,
            'public, s-maxage=64000',
            'public, s-maxage=64000',
        ];

        yield 'cache proxy, cache-control is not touched #3' => [
            true,
            'private, no-store',
            'no-store, private', // Symfony sorts the cache-control
        ];
    }

    public function testAddHttpCacheToCoreRoutes(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            1,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $request = new Request();
        $request->attributes->set('_route', 'api.acl.privileges.get');
        $subscriber->addHttpCacheToCoreRoutes(new RequestEvent($this->createMock(KernelInterface::class), $request, KernelInterface::MAIN_REQUEST));

        static::assertTrue($request->attributes->has('_' . HttpCache::ALIAS));
    }

    /**
     * @dataProvider providerCurrencyChange
     */
    public function testCurrencyChange(?string $currencyId): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $request = new Request();
        $request->query->set(SalesChannelContextService::CURRENCY_ID, $currencyId);
        $request->attributes->set('_route', 'frontend.checkout.configure');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        if ($currencyId === null) {
            static::assertEmpty($cookies);
        } else {
            static::assertNotEmpty($cookies);
            static::assertSame($currencyId, $cookies[0]->getValue());
        }
    }

    public function providerCurrencyChange(): iterable
    {
        yield 'no currency' => [null];
        yield 'currency' => [Defaults::CURRENCY];
    }

    public function testStatesGetDeletedOnEmptyState(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(CacheResponseSubscriber::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();

        static::assertCount(1, $cookies);
        static::assertNull($cookies[0]->getValue());
        static::assertSame(1, $cookies[0]->getExpiresTime());
    }

    /**
     * @dataProvider notCacheableRequestProvider
     */
    public function testNotCacheablePages(Request $request): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertEmpty($response->headers->getCookies(), var_export($response->headers->getCookies(), true));
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    public function notCacheableRequestProvider(): iterable
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $postRequest = new Request([], [], [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext]);
        $postRequest->setMethod(Request::METHOD_POST);

        yield 'admin request' => [new Request([], [], ['_route' => 'admin.dashboard.index'])];
        yield 'post request' => [$postRequest];
    }

    public function testNoCachingWhenInvalidateStateMatches(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cart = new Cart('test', 'test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $cartService->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            $cartService,
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            null,
            null
        );

        $request = new Request();
        $request->attributes->set('_' . HttpCache::ALIAS, [new HttpCache([
            'states' => ['cart-filled'],
        ])]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(CacheResponseSubscriber::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        static::assertSame(0, $cookies[0]->getExpiresTime(), 'the cookie should be an session cookie');

        // still not cached
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    public function testMakeGetsCached(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            $this->createMock(MaintenanceModeResolver::class),
            false,
            '5',
            '6'
        );

        $request = new Request();
        $request->attributes->set('_' . HttpCache::ALIAS, [new HttpCache([])]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(CacheResponseSubscriber::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame('must-revalidate, public, s-maxage=100, stale-if-error=6, stale-while-revalidate=5', $response->headers->get('cache-control'));
    }
}
