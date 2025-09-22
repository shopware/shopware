<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(CacheResponseSubscriber::class)]
class CacheResponseSubscriberTest extends TestCase
{
    private const IP = '127.0.0.1';

    /**
     * @var array<string, string>
     */
    private static array $hashes = [];

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerLogoutEvent::class => 'onCustomerLogout',
        ];

        static::assertSame($expected, CacheResponseSubscriber::getSubscribedEvents());
    }

    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            false,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = new Response();
        $expectedHeaders = $response->headers->all();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        static::assertSame($expectedHeaders, $response->headers->all());
    }

    public function testNoAutoCacheControlHeader(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->setResponseCacheHeader($event);

        static::assertSame('1', $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderCacheDisabled(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            false,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderNoHttpCacheRoute(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => false]);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    #[DataProvider('cashHashProvider')]
    public function testGenerateCashHashWithItemsInCart(?CustomerEntity $customer, Cart $cart, bool $hasCookie, ?string $hashName = null): void
    {
        $service = $this->createMock(CartService::class);
        $service->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            [],
            $service,
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        if ($hasCookie === false) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        if ($hasCookie) {
            static::assertTrue($response->headers->has('set-cookie'));

            $cookies = array_filter($response->headers->getCookies(), fn (Cookie $cookie) => $cookie->getName() === HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);

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
                        static::assertSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" did not match, got "%s", but expected "%s"', $hashName, $cookie->getValue(), $value)
                        );
                    } else {
                        static::assertNotSame(
                            $value,
                            $cookie->getValue(),
                            \sprintf('Hashes for state "%s" and state "%s" should not match, but did match.', $hashName, $name)
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
     * @param string[] $whitelist
     */
    #[DataProvider('maintenanceRequest')]
    public function testMaintenanceRequest(bool $active, array $whitelist, bool $shouldBeCached): void
    {
        $cartService = $this->createMock(CartService::class);
        $requestStack = new RequestStack();

        $subscriber = new CacheResponseSubscriber(
            [],
            $cartService,
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            $requestStack,
            null,
            null,
            new EventDispatcher()
        );

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $active);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, \json_encode($whitelist, \JSON_THROW_ON_ERROR));
        $request->server->set('REMOTE_ADDR', self::IP);
        $requestStack->push($request);

        static::assertSame(self::IP, $request->getClientIp());

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $cart = new Cart('token');

        $count = $shouldBeCached ? 1 : 0;

        $cartService->expects($this->exactly($count))
            ->method('getCart')
            ->willReturn($cart);

        $subscriber->setResponseCache($event);
    }

    public function testOnCustomerLogin(): void
    {
        $requestStack = new RequestStack();

        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            $requestStack,
            null,
            null,
            new EventDispatcher()
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $request = new Request();
        $requestStack->push($request);

        $event = new CustomerLoginEvent($salesChannelContext, new CustomerEntity(), 'token');
        $subscriber->onCustomerLogin($event);

        static::assertSame($salesChannelContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }

    /**
     * @return array<string, array<int, CustomerEntity|Cart|bool|string|null>>
     */
    public static function cashHashProvider(): iterable
    {
        $emptyCart = new Cart('empty');
        $customer = new CustomerEntity();

        $filledCart = new Cart('filled');
        $filledCart->add(new LineItem('test', 'test', 'test'));

        yield 'Test with no logged in customer' => [null, $emptyCart, false];
        yield 'Test with filled cart' => [null, $filledCart, true, 'not-logged-in'];
        // all logged in customer should share the same cache hash if no rules match
        yield 'Test with logged in customer' => [$customer, $emptyCart, true, 'logged-in'];
        yield 'Test with filled cart and logged in customer' => [$customer, $filledCart, true, 'logged-in'];
    }

    /**
     * @return array<string, array<int, bool|string[]>>
     */
    public static function maintenanceRequest(): iterable
    {
        yield 'Always cache requests when maintenance is inactive' => [false, [], true];
        yield 'Always cache requests when maintenance is active' => [true, [], true];
        yield 'Do not cache requests of whitelisted ip' => [true, [self::IP], false];
        yield 'Cache requests if ip is not whitelisted' => [true, ['120.0.0.0'], true];
    }

    #[DataProvider('providerCurrencyChange')]
    public function testCurrencyChange(?string $currencyId): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
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

    /**
     * @return array<string, array<int, string|null>>
     */
    public static function providerCurrencyChange(): iterable
    {
        yield 'no currency' => [null];
        yield 'currency' => [Defaults::CURRENCY];
    }

    public function testStatesGetDeletedOnEmptyState(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

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

    #[DataProvider('notCacheableRequestProvider')]
    public function testNotCacheablePages(Request $request): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
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

    /**
     * @return array<string, array<int, Request>>
     */
    public static function notCacheableRequestProvider(): iterable
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign(['customer' => null]);

        $postRequest = new Request([], [], [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext]);
        $postRequest->setMethod(Request::METHOD_POST);

        yield 'admin request' => [new Request([], [], ['_route' => 'admin.dashboard.index'])];
        yield 'post request' => [$postRequest];
    }

    #[DataProvider('cookiesUntouchedProvider')]
    public function testCookiesAreUntouched(Request $request, ?Response $response = null): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        if (!$response) {
            $response = new Response();
        }

        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertEmpty($response->headers->getCookies(), var_export($response->headers->getCookies(), true));
        static::assertFalse($response->headers->has('set-cookie'));
    }

    /**
     * @return array<string, array{0: Request, 1?: Response}>
     */
    public static function cookiesUntouchedProvider(): iterable
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign(['customer' => null]);

        $salesChannelRequest = new Request([], [], [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext]);
        $salesChannelRequest->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        $salesChannelRequest->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'logged-in');

        $maintenanceRequest = clone $salesChannelRequest;
        $maintenanceRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, true);
        $maintenanceRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, \json_encode([self::IP, \JSON_THROW_ON_ERROR]));
        $maintenanceRequest->server->set('REMOTE_ADDR', self::IP);

        yield 'no sales channel context' => [new Request()];
        yield 'maintenance request' => [$maintenanceRequest];
        yield 'not found response' => [$salesChannelRequest, new Response('', Response::HTTP_NOT_FOUND)];
    }

    public function testNoCachingWhenInvalidateStateMatches(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cart = new Cart('test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $cartService->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            [],
            $cartService,
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, [
            'states' => ['cart-filled'],
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        static::assertSame(0, $cookies[0]->getExpiresTime(), 'the cookie should be an session cookie');

        // still not cached
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    public function testMakeGetsCached(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            '5',
            '6',
            new EventDispatcher()
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame('public, s-maxage=100, stale-if-error=6, stale-while-revalidate=5', $response->headers->get('cache-control'));
    }

    /**
     * @return array<string, array{
     *     route: string,
     *     requestMethod: string,
     *     cookiesAmount: int,
     *     cookieName: string,
     *     assertCountErrorMessage: string,
     *     assertEqualsErrorMessage: string
     * }>
     */
    public static function providerSetResponseCacheOnLogin(): iterable
    {
        yield 'Don\'t set the cache on no_login via post' => [
            'route' => 'no.login',
            'requestMethod' => Request::METHOD_POST,
            'cookiesAmount' => 1,
            'cookieName' => HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE,
            'assertCountErrorMessage' => 'There should be 1 cookies set now!',
            'assertEqualsErrorMessage' => 'HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE should be set as 1. cookie',
        ];

        yield 'Set cache on login via post' => [
            'route' => 'frontend.account.login',
            'requestMethod' => Request::METHOD_POST,
            'cookiesAmount' => 2,
            'cookieName' => HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
            'assertCountErrorMessage' => 'There should be 2 cookies set now!',
            'assertEqualsErrorMessage' => 'HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE should be set as 2. cookie',
        ];

        yield 'Set cache on no_login via get' => [
            'route' => 'anything',
            'requestMethod' => Request::METHOD_GET,
            'cookiesAmount' => 2,
            'cookieName' => HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
            'assertCountErrorMessage' => 'There should be 2 cookies set now!',
            'assertEqualsErrorMessage' => 'HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE should be set as 2. cookie',
        ];

        yield 'Set cache on login via get' => [
            'route' => 'frontend.account.login',
            'requestMethod' => Request::METHOD_GET,
            'cookiesAmount' => 2,
            'cookieName' => HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
            'assertCountErrorMessage' => 'There should be 2 cookies set now!',
            'assertEqualsErrorMessage' => 'HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE should be set as 2. cookie',
        ];
    }

    #[DataProvider('providerSetResponseCacheOnLogin')]
    public function testSetResponseCacheOnLogin(
        string $route,
        string $requestMethod,
        int $cookiesAmount,
        string $cookieName,
        string $assertCountErrorMessage,
        string $assertEqualsErrorMessage
    ): void {
        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher()
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getCustomer')
            ->willReturn(new CustomerEntity());
        $request = new Request();
        $request->setMethod($requestMethod);
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            $salesChannelContext
        );
        $request->attributes->set('_route', $route);

        $response = new Response();
        $subscriber->setResponseCache(
            new ResponseEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        static::assertCount(
            $cookiesAmount,
            $response->headers->getCookies(),
            $assertCountErrorMessage
        );
        static::assertSame(
            $cookieName,
            $response->headers->getCookies()[$cookiesAmount - 1]->getName(),
            $assertEqualsErrorMessage
        );
    }

    public function testRequestContextGetsUpdatedWhileLogout(): void
    {
        $customer = new CustomerEntity();
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => $customer]);
        $event = new CustomerLogoutEvent($context, $customer);

        $requestStack = new RequestStack();
        $request = new Request();

        $requestStack->push($request);

        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            $requestStack,
            null,
            null,
            new EventDispatcher()
        );

        $subscriber->onCustomerLogout($event);

        $requestContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        static::assertInstanceOf(SalesChannelContext::class, $requestContext);
        static::assertNull($requestContext->getCustomer());
        static::assertNull($requestContext->getCustomerId());
    }

    /**
     * @param array<string, mixed> $requestResponseOptions
     * @param array<string, mixed> $subscriberConfig
     */
    #[DataProvider('storeApiCachingProvider')]
    public function testStoreApiCaching(
        array $requestResponseOptions,
        array $subscriberConfig,
        string $expectedCacheControl,
    ): void {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            $subscriberConfig['defaultTtl'] ?? 100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            $subscriberConfig['staleWhileRevalidate'] ?? null,
            $subscriberConfig['staleIfError'] ?? null,
            new EventDispatcher(),
            $subscriberConfig['storeApiDefaultTtl'] ?? null,
            $subscriberConfig['storeApiStaleWhileRevalidate'] ?? null,
            $subscriberConfig['storeApiStaleIfError'] ?? null,
        );

        $request = new Request();
        $response = new Response();
        foreach ($requestResponseOptions as $key => $value) {
            if ($key === '_method') {
                $request->setMethod($value);
            } elseif ($key === 'responseOriginalCacheControl') {
                $response->headers->set('cache-control', $requestResponseOptions['responseOriginalCacheControl']);
            } else {
                $request->attributes->set($key, $value);
            }
        }

        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame($expectedCacheControl, $response->headers->get('cache-control'));
        static::assertEmpty($response->headers->getCookies(), 'Should not have cookies');
    }

    /**
     * @return iterable<array{
     *      requestResponseOptions: array<string, mixed>,
     *      subscriberConfig: array<string, mixed>,
     *      expectedCacheControl: string
     *  }>
     */
    public static function storeApiCachingProvider(): iterable
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $baseRequestAttributes = [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        ];

        yield 'Store API with defaults' => [
            'requestResponseOptions' => $baseRequestAttributes,
            'subscriberConfig' => [],
            'expectedCacheControl' => 'public, s-maxage=100',
        ];

        yield 'Store API defaultTtl is used when no storeApiDefaultTtl' => [
            'requestResponseOptions' => $baseRequestAttributes,
            'subscriberConfig' => ['defaultTtl' => 200],
            'expectedCacheControl' => 'public, s-maxage=200',
        ];

        yield 'Store API storeApiDefaultTtl option has priority over defaultTtl' => [
            'requestResponseOptions' => $baseRequestAttributes,
            'subscriberConfig' => ['defaultTtl' => 200, 'storeApiDefaultTtl' => 300],
            'expectedCacheControl' => 'public, s-maxage=300',
        ];

        yield 'Store API staleWhileRevalidate, staleIfError ignored' => [
            'requestResponseOptions' => $baseRequestAttributes,
            'subscriberConfig' => ['defaultTtl' => 200, 'staleWhileRevalidate' => '5', 'staleIfError' => '6'],
            'expectedCacheControl' => 'public, s-maxage=200',
        ];

        yield 'Store API storeApiStaleWhileRevalidate, storeApiStaleIfError used' => [
            'requestResponseOptions' => $baseRequestAttributes,
            'subscriberConfig' => ['defaultTtl' => 200, 'storeApiStaleWhileRevalidate' => '5', 'storeApiStaleIfError' => '6'],
            'expectedCacheControl' => 'public, s-maxage=200, stale-if-error=6, stale-while-revalidate=5',
        ];

        yield 'Store API custom endpoint maxAge has priority' => [
            'requestResponseOptions' => array_merge($baseRequestAttributes, [
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => ['maxAge' => 300],
            ]),
            'subscriberConfig' => ['defaultTtl' => 200],
            'expectedCacheControl' => 'public, s-maxage=300',
        ];

        yield 'Store API preconfigured no-cache directive removed' => [
            'requestResponseOptions' => array_merge($baseRequestAttributes, [
                'responseOriginalCacheControl' => 'no-cache, private',
            ]),
            'subscriberConfig' => [],
            'expectedCacheControl' => 'public, s-maxage=100',
        ];

        yield 'Store API POST is not cached' => [
            'requestResponseOptions' => array_merge($baseRequestAttributes, ['_method' => Request::METHOD_POST]),
            'subscriberConfig' => ['staleWhileRevalidate' => '5', 'staleIfError' => '6'],
            'expectedCacheControl' => 'no-cache, private',
        ];

        yield 'Store API endpoints without SalesChannelContext are not cached' => [
            'requestResponseOptions' => [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => null,
            ],
            'subscriberConfig' => [],
            'expectedCacheControl' => 'no-cache, private',
        ];

        yield 'Store API endpoints without cache attributes are not cached' => [
            'requestResponseOptions' => [
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => null,
            ],
            'subscriberConfig' => [],
            'expectedCacheControl' => 'no-cache, private',
        ];
    }

    #[DisabledFeatures(['STORE_API_CACHE'])]
    public function testStoreApiBehavesLikeStorefrontWithoutFeatureFlag(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cart = new Cart('test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $cartService->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            [],
            $cartService,
            100,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            '5',
            '6',
            new EventDispatcher(),
            300,
            '10',
            '15',
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        // Test Store API request without feature flag - should behave like Storefront
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set('_route', 'store-api.test'); // Set route to allow normal flow

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        // Without feature flag, Store API should behave like Storefront and set cookies
        static::assertSame('public, s-maxage=100, stale-if-error=6, stale-while-revalidate=5', $response->headers->get('cache-control'));
        static::assertNotEmpty($response->headers->getCookies(), 'Without feature flag, Store API should set cookies like Storefront');

        // Verify the exact cookies that should be set
        $cookies = $response->headers->getCookies();
        $cookieNames = array_map(fn (Cookie $cookie) => $cookie->getName(), $cookies);

        static::assertContains(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, $cookieNames, 'Should set system state cookie');
        static::assertContains(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookieNames, 'Should set context cache cookie');
    }

    public function testStoreApiCachingIgnoresStatesAndCookies(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cart = new Cart('test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $cartService->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            [],
            $cartService,
            200,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher(),
            100,
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        // Test Store API with states that would normally prevent caching
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, [
            'states' => ['cart-filled', 'logged-in'],
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled,logged-in');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        // Store API should cache regardless of states and not set any cookies
        static::assertSame('public, s-maxage=100', $response->headers->get('cache-control'));
        static::assertEmpty($response->headers->getCookies(), 'Store API should not set state cookies even with filled cart and logged-in customer');
    }

    public function testStoreApiCachingIgnoresContextCacheCookies(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cart = new Cart('test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $cartService->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            [],
            $cartService,
            200,
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            new RequestStack(),
            null,
            null,
            new EventDispatcher(),
            100,
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());

        // Test Store API with existing context cache cookie
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'existing-hash');

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        // Store API should cache and not set/update context cache cookies
        static::assertSame('public, s-maxage=100', $response->headers->get('cache-control'));
        static::assertEmpty($response->headers->getCookies(), 'Store API should not set context cache cookies');
    }
}
