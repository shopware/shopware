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
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(CacheResponseSubscriber::class)]
#[CoversClass(HttpCacheCookieEvent::class)]
class CacheResponseSubscriberTest extends TestCase
{
    use EventDispatcherBehaviour;

    private const IP = '127.0.0.1';

    /**
     * @var array<string, string>
     */
    private static array $hashes = [];

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerLogoutEvent::class => 'onCustomerLogout',
            SalesChannelContextSwitchEvent::class => 'onContextSwitched',
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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = new Response();
        $expectedHeaders = $response->headers->all();

        $event = $this->createResponseEvent($request, $response);

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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => false]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        if ($customer !== null) {
            $salesChannelContext->expects($this->once())
                ->method('getRuleIdsByAreas')
                ->with([RuleAreas::PRODUCT_AREA])
                ->willReturn(['matched-rule']);
        }

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        if ($hasCookie === false) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $subscriber->setResponseCache($event);

        if ($hasCookie) {
            static::assertTrue($response->headers->has('set-cookie'));

            $cookies = array_filter($response->headers->getCookies(), fn (Cookie $cookie) => $cookie->getName() === HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);

            static::assertCount(1, $cookies);
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
            new MaintenanceModeResolver($this->eventDispatcher),
            $requestStack,
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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

        $event = $this->createResponseEvent($request, $response);

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
            new MaintenanceModeResolver($this->eventDispatcher),
            $requestStack,
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $request = new Request();
        $requestStack->push($request);

        $event = new CustomerLoginEvent($salesChannelContext, new CustomerEntity(), 'token');
        $subscriber->onCustomerLogin($event);

        static::assertSame($salesChannelContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }

    public function testOnContextSwitched(): void
    {
        $requestStack = new RequestStack();

        $updatedContext = $this->createMock(SalesChannelContext::class);

        $contextServiceMock = $this->createMock(SalesChannelContextService::class);
        $contextServiceMock->expects($this->once())
            ->method('get')
            ->with(new SalesChannelContextServiceParameters(
                'test-sales-channel',
                'test-token',
            ))
            ->willReturn($updatedContext);

        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            $requestStack,
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $contextServiceMock
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('test-sales-channel');
        $salesChannelContext->method('getToken')
            ->willReturn('test-token');

        $request = new Request();
        $requestStack->push($request);

        $event = new SalesChannelContextSwitchEvent($salesChannelContext, new RequestDataBag());
        $subscriber->onContextSwitched($event);

        static::assertSame($updatedContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }

    /**
     * @return iterable<string, array<int, CustomerEntity|Cart|bool|string|null>>
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
     * @return iterable<string, array<int, bool|string[]>>
     */
    public static function maintenanceRequest(): iterable
    {
        yield 'Always cache requests when maintenance is inactive' => [false, [], true];
        yield 'Always cache requests when maintenance is active' => [true, [], true];
        yield 'Do not cache requests of whitelisted ip' => [true, [self::IP], false];
        yield 'Cache requests if ip is not whitelisted' => [true, ['120.0.0.0'], true];
    }

    #[DataProvider('providerCurrencyChange')]
    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_CONTEXT_HASH_RULES_OPTIMIZATION'])]
    /**
     * @deprecated tag:v6.8.0 - can be removed as currency cookie is no longer used
     */
    public function testCurrencyChange(?string $currencyId): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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

    public function testCurrencyChangeLeadsToDifferentCacheHash(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $request = new Request();
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        static::assertEmpty($cookies);

        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn('foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        // assert cache hash exist when currency is set to different value then the sales channel default
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $salesChannelContextMock->method('getSalesChannel')->willReturn((new SalesChannelEntity())->assign(['currencyId' => Defaults::CURRENCY]));
        $salesChannelContextMock->method('getCurrencyId')->willReturn('bar');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContextMock);

        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $secondHash = $cookies[0]->getValue();
        // assert cache hash is different when currency id is different
        static::assertNotSame($firstHash, $secondHash);
    }

    /**
     * @return iterable<string, array<int, string|null>>
     */
    public static function providerCurrencyChange(): iterable
    {
        yield 'no currency' => [null];
        yield 'currency' => [Defaults::CURRENCY];
    }

    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_CONTEXT_HASH_RULES_OPTIMIZATION'])]
    public function testStatesGetDeletedOnEmptyState(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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
     * @return iterable<string, array<int, Request>>
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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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
     * @return iterable<string, array{0: Request, 1?: Response}>
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

    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_CONTEXT_HASH_RULES_OPTIMIZATION'])]
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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            '5',
            '6',
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
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
     * @return iterable<string, array{
     *     requestMethod: string,
     * }>
     */
    public static function providerSetResponseCacheOnLoginDeprecated(): iterable
    {
        yield 'Set cache on login via post' => [
            'requestMethod' => Request::METHOD_POST,
        ];

        yield 'Set cache on login via get' => [
            'requestMethod' => Request::METHOD_GET,
        ];
    }

    /**
     * @return iterable<string, array{
     *     requestMethod: string,
     * }>
     */
    public static function providerSetResponseCacheOnLogin(): iterable
    {
        yield 'Set cache on login via post' => [
            'requestMethod' => Request::METHOD_POST,
        ];

        yield 'Set cache on login via get' => [
            'requestMethod' => Request::METHOD_GET,
        ];
    }

    #[DataProvider('providerSetResponseCacheOnLoginDeprecated')]
    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_CONTEXT_HASH_RULES_OPTIMIZATION'])]
    public function testSetResponseCacheOnLoginDeprecated(
        string $requestMethod,
    ): void {
        $request = new Request();
        $request->setMethod($requestMethod);

        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack([$request]),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getCustomer')
            ->willReturn(new CustomerEntity());

        $subscriber->onCustomerLogin(
            new CustomerLoginEvent(
                $salesChannelContext,
                new CustomerEntity(),
                'test'
            )
        );

        $response = new Response();
        $subscriber->setResponseCache(
            new ResponseEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        static::assertCount(2, $response->headers->getCookies());
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $response->headers->getCookies()[1]->getName());
    }

    #[DataProvider('providerSetResponseCacheOnLogin')]
    public function testSetResponseCacheOnLogin(
        string $requestMethod,
    ): void {
        $request = new Request();
        $request->setMethod($requestMethod);

        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack([$request]),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getCustomer')
            ->willReturn(new CustomerEntity());

        $subscriber->onCustomerLogin(
            new CustomerLoginEvent(
                $salesChannelContext,
                new CustomerEntity(),
                'test'
            )
        );

        $response = new Response();
        $subscriber->setResponseCache(
            new ResponseEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        static::assertCount(1, $response->headers->getCookies());
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $response->headers->getCookies()[0]->getName());
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
            new MaintenanceModeResolver($this->eventDispatcher),
            $requestStack,
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $subscriber->onCustomerLogout($event);

        $requestContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        static::assertInstanceOf(SalesChannelContext::class, $requestContext);
        static::assertNull($requestContext->getCustomer());
        static::assertNull($requestContext->getCustomerId());
    }

    public function testCacheCookieStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $firstResponse = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $firstResponse));

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, function (HttpCacheCookieEvent $event): void {
            $ruleIds = $event->get('rule-ids');
            self::assertIsArray($ruleIds);
            $event->remove('rule-ids');
            $event->add('rule-ids', $ruleIds);
        });

        $secondResponse = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $secondResponse));

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
    }

    public function testCacheCookieHasNoCacheValueIfSetInEvent(): void
    {
        $subscriber = new CacheResponseSubscriber(
            [],
            static::createStub(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            new RequestStack(),
            null,
            null,
            $this->eventDispatcher,
            new CacheRelevantRulesResolver(new ExtensionDispatcher($this->eventDispatcher)),
            $this->createMock(SalesChannelContextService::class)
        );

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $firstResponse = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $firstResponse));

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, function (HttpCacheCookieEvent $event): void {
            $event->isCacheable = false;
        });

        $secondResponse = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $secondResponse));

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertNotSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $secondCacheCookie->getValue());
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }
}
