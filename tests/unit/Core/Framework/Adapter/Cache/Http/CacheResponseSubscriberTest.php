<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicy;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\DefaultPolicies;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-import-type CachePolicyConfig from CachePolicy
 * @phpstan-import-type DefaultPoliciesConfig from DefaultPolicies
 * @phpstan-import-type CacheAttributeType from CacheAttribute
 */
#[CoversClass(CacheResponseSubscriber::class)]
#[CoversClass(HttpCacheCookieEvent::class)]
class CacheResponseSubscriberTest extends TestCase
{
    private const IP = '127.0.0.1';

    private EventDispatcher $eventDispatcher;

    private CacheResponseSubscriber $subscriber;

    private CartService&MockObject $cartService;

    private CacheHeadersService&MockObject $cacheHeadersService;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->cartService = $this->createMock(CartService::class);
        $this->cacheHeadersService = $this->createMock(CacheHeadersService::class);

        $this->subscriber = new CacheResponseSubscriber(
            $this->cartService,
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            '5',
            '6',
            $this->cacheHeadersService,
            $this->createCachePolicyProvider(),
        );
    }

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
        ];

        static::assertSame($expected, CacheResponseSubscriber::getSubscribedEvents());
    }

    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = $this->getCacheResponseSubscriberWithCacheDisabled();

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

    public function testNoStoreAppliedWhenCacheDisabled(): void
    {
        $subscriber = $this->getCacheResponseSubscriberWithCacheDisabled();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $subscriber->setResponseCache($event);

        // Verify no-store headers are applied even when cache is disabled
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        static::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        static::assertFalse($response->isCacheable());
    }

    public function testNoAutoCacheControlHeader(): void
    {
        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->setResponseCacheHeader($event);

        static::assertSame('1', $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderCacheDisabled(): void
    {
        $subscriber = $this->getCacheResponseSubscriberWithCacheDisabled();

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $this->cacheHeadersService->expects($this->never())
            ->method('applyCacheHash');

        $subscriber->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderNoHttpCacheRoute(): void
    {
        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => false]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $this->cacheHeadersService->expects($this->never())
            ->method('applyCacheHash');

        $this->subscriber->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    /**
     * @param string[] $whitelist
     */
    #[DataProvider('maintenanceRequest')]
    public function testMaintenanceRequest(bool $active, array $whitelist, bool $shouldBeCached): void
    {
        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $active);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, \json_encode($whitelist, \JSON_THROW_ON_ERROR));
        $request->server->set('REMOTE_ADDR', self::IP);

        static::assertSame(self::IP, $request->getClientIp());

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $cart = new Cart('token');

        $count = $shouldBeCached ? 1 : 0;

        $this->cartService->expects($this->exactly($count))
            ->method('getCart')
            ->willReturn($cart);

        if ($shouldBeCached) {
            $this->cacheHeadersService->expects($this->once())
                ->method('applyCacheHash');
        }

        $this->subscriber->setResponseCache($event);
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
    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_REWORK'])]
    /**
     * @deprecated tag:v6.8.0 - can be removed as currency cookie is no longer used
     */
    public function testCurrencyChange(?string $currencyId): void
    {
        $request = new Request();
        $request->query->set(SalesChannelContextService::CURRENCY_ID, $currencyId);
        $request->attributes->set('_route', 'frontend.checkout.configure');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));

        $response = new Response();
        $this->subscriber->setResponseCache(new ResponseEvent(
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
     * @return iterable<string, array<int, string|null>>
     */
    public static function providerCurrencyChange(): iterable
    {
        yield 'no currency' => [null];
        yield 'currency' => [Defaults::CURRENCY];
    }

    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_REWORK'])]
    public function testStatesGetDeletedOnEmptyState(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $this->subscriber->setResponseCache(new ResponseEvent(
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

    public function testAdminPagesNotCached(): void
    {
        $request = new Request([], [], ['_route' => 'admin.dashboard.index']);
        $response = new Response();

        $this->cacheHeadersService->expects($this->never())
            ->method('applyCacheHash');

        $this->subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertEmpty($response->headers->getCookies(), var_export($response->headers->getCookies(), true));
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    #[DataProvider('cookiesUntouchedProvider')]
    public function testCookiesAreUntouched(Request $request, ?Response $response = null): void
    {
        if (!$response) {
            $response = new Response();
        }

        $this->cacheHeadersService->expects($this->never())
            ->method('applyCacheHash');

        $this->subscriber->setResponseCache(new ResponseEvent(
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

    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_REWORK'])]
    public function testNoCachingWhenInvalidateStateMatches(): void
    {
        $cart = new Cart('test');
        $cart->add(new LineItem('test', 'test', 'test', 1));
        $this->cartService->method('getCart')->willReturn($cart);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, new CacheAttribute(
            states: ['cart-filled'],
        ));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $response = new Response();
        $this->subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        // still not cached
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['CACHE_REWORK', 'v6.8.0.0'])]
    public function testMakeGetsCached(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $this->cacheHeadersService->expects($this->once())
            ->method('applyCacheHash');

        $response = new Response();
        $this->subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame('public, s-maxage=100, stale-if-error=6, stale-while-revalidate=5', $response->headers->get('cache-control'));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DataProvider('noStoreWithoutCacheReworkProvider')]
    #[DisabledFeatures(['CACHE_REWORK', 'v6.8.0.0'])]
    public function testNoStoreAppliedWithoutCacheRework(string $method, bool $withHttpCache): void
    {
        $request = new Request();
        $request->setMethod($method);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);

        if ($withHttpCache) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        }

        $response = new Response();
        $this->subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        static::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        static::assertFalse($response->isCacheable());
    }

    /**
     * @return iterable<string, array{method: string, withHttpCache: bool}>
     */
    public static function noStoreWithoutCacheReworkProvider(): iterable
    {
        yield 'GET route with cache attribute' => ['method' => Request::METHOD_GET, 'withHttpCache' => true];
        yield 'GET route without cache attribute' => ['method' => Request::METHOD_GET, 'withHttpCache' => false];
        yield 'POST route' => ['method' => Request::METHOD_POST, 'withHttpCache' => false];
    }

    /**
     * @param array<string, mixed> $requestResponseOptions
     * @param array{
     *     policies?: array<string, CachePolicyConfig>,
     *     defaultPolicies?: array<string, DefaultPoliciesConfig>,
     *     routePolicies?: array<string, string>,
     *     defaultTtl?: int,
     *     staleWhileRevalidate?: string|null,
     *     staleIfError?: string|null
     * } $subscriberConfig
     */
    #[DataProvider('cachePoliciesAppliedProvider')]
    public function testCachePoliciesApplied(
        array $requestResponseOptions,
        array $subscriberConfig,
        string $expectedCacheControl,
    ): void {
        $policyProvider = $this->createCachePolicyProvider(
            $subscriberConfig['policies'] ?? [],
            $subscriberConfig['defaultPolicies'] ?? [],
            $subscriberConfig['routePolicies'] ?? [],
        );

        // manually create instance with custom configured policy provider
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            $subscriberConfig['defaultTtl'] ?? 100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            $subscriberConfig['staleWhileRevalidate'] ?? null,
            $subscriberConfig['staleIfError'] ?? null,
            $this->cacheHeadersService,
            $policyProvider,
        );

        $request = new Request();
        $response = new Response();
        foreach ($requestResponseOptions as $key => $value) {
            if ($key === '_method') {
                $request->setMethod($value);
            } elseif ($key === 'responseOriginalCacheControl') {
                $response->headers->set('cache-control', $value);
            } else {
                $request->attributes->set($key, $value);
            }
        }

        // determine if storefront route
        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        $this->cacheHeadersService->expects($this->once())
            ->method('applyCacheHash');

        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        // Check Cache-Control header
        static::assertSame($expectedCacheControl, $response->headers->get('cache-control'));

        // Check cookies absence for non-storefront routes
        static::assertIsArray($routeScope);
        static::assertEmpty($response->headers->getCookies(), 'Should not have cookies');
        static::assertFalse($response->headers->has(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS));
    }

    /**
     * @return iterable<string, array{
     *      requestResponseOptions: array<string, mixed>,
     *      subscriberConfig: array{
     *          policies?: array<string, CachePolicyConfig>,
     *          defaultPolicies?: array<string, DefaultPoliciesConfig>,
     *          routePolicies?: array<string, string>,
     *          defaultTtl?: int,
     *          staleWhileRevalidate?: string|null,
     *          staleIfError?: string|null
     *      },
     *      expectedCacheControl: string
     *  }>
     */
    public static function cachePoliciesAppliedProvider(): iterable
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $storefrontRequestAttributes = [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ];

        $basePolicies = [
            'p_default' => [
                'headers' => [
                    'cache_control' => [
                        'public' => true,
                        's_maxage' => 200,
                    ],
                ],
            ],
            'p_storefront' => [
                'headers' => [
                    'cache_control' => [
                        'public' => true,
                        's_maxage' => 100,
                    ],
                ],
            ],
            'no_cache_private' => [
                'headers' => [
                    'cache_control' => [
                        'private' => true,
                        'no_cache' => true,
                        'max_age' => 0,
                        's_maxage' => 0,
                    ],
                ],
            ],
            // route specific policy
            'p_route' => [
                'headers' => [
                    'cache_control' => [
                        'public' => true,
                        's_maxage' => 300,
                        'stale_while_revalidate' => 10,
                    ],
                ],
            ],
            // scripts policy with modifier
            'p_script_blog' => [
                'headers' => [
                    'cache_control' => [
                        'public' => true,
                        's_maxage' => 600,
                        'stale_while_revalidate' => 20,
                    ],
                ],
            ],
        ];

        $defaultPolicies = [
            'storefront' => [
                'cacheable' => 'p_storefront',
                'uncacheable' => 'no_cache_private',
            ],
            'store_api' => [
                'cacheable' => 'p_default',
                'uncacheable' => 'no_cache_private',
            ],
        ];

        yield 'Storefront policy applied' => [
            'requestResponseOptions' => $storefrontRequestAttributes,
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'public, s-maxage=100',
        ];

        // Storefront policyModifier tests
        yield 'Storefront policyModifier allows route-specific policies with modifiers' => [
            'requestResponseOptions' => array_merge($storefrontRequestAttributes, [
                '_route' => 'frontend.script_endpoint',
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => new CacheAttribute(
                    policyModifier: 'blog-update',
                ),
            ]),
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
                'routePolicies' => [
                    'frontend.script_endpoint' => 'p_route',
                    'frontend.script_endpoint#blog-update' => 'p_script_blog',
                ],
            ],
            'expectedCacheControl' => 'public, s-maxage=600, stale-while-revalidate=20',
        ];

        yield 'Storefront policyModifier falls back to route policy when modifier-specific policy not found' => [
            'requestResponseOptions' => array_merge($storefrontRequestAttributes, [
                '_route' => 'frontend.script_endpoint',
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => new CacheAttribute(
                    policyModifier: 'nonexistent-hook',
                ),
            ]),
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
                'routePolicies' => [
                    'frontend.script_endpoint' => 'p_route',
                ],
            ],
            'expectedCacheControl' => 'public, s-maxage=300, stale-while-revalidate=10',
        ];

        yield 'Storefront POST is not cached (uncacheable policy)' => [
            'requestResponseOptions' => array_merge($storefrontRequestAttributes, ['_method' => Request::METHOD_POST]),
            'subscriberConfig' => [
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'max-age=0, no-cache, private, s-maxage=0',
        ];

        $storeApiRequestAttributes = [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        ];

        yield 'Store API policy applied' => [
            'requestResponseOptions' => $storeApiRequestAttributes,
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'public, s-maxage=200',
        ];

        yield 'Store API policy overwrites response cache-control' => [
            'requestResponseOptions' => array_merge($storeApiRequestAttributes, [
                'responseOriginalCacheControl' => 'no-cache, private',
            ]),
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'public, s-maxage=200',
        ];

        // route specific policy should override defaults
        yield 'Store API route-specific policy overrides defaults' => [
            'requestResponseOptions' => array_merge($storeApiRequestAttributes, [
                '_route' => 'store-api.product.search',
            ]),
            'subscriberConfig' => [
                'defaultTtl' => 100,
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
                'routePolicies' => [
                    'store-api.product.search' => 'p_route',
                ],
            ],
            'expectedCacheControl' => 'public, s-maxage=300, stale-while-revalidate=10',
        ];

        yield 'Store API POST is not cached (uncacheable policy)' => [
            'requestResponseOptions' => array_merge($storeApiRequestAttributes, ['_method' => Request::METHOD_POST]),
            'subscriberConfig' => [
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'max-age=0, no-cache, private, s-maxage=0',
        ];

        yield 'Store API endpoints without cache attributes are not cached (uncacheable policy)' => [
            'requestResponseOptions' => [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => null,
            ],
            'subscriberConfig' => [
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'max-age=0, no-cache, private, s-maxage=0',
        ];

        yield 'no-store attribute enforces noStore policy' => [
            'requestResponseOptions' => array_merge($storefrontRequestAttributes, [
                PlatformRequest::ATTRIBUTE_NO_STORE => true,
            ]),
            'subscriberConfig' => [
                'policies' => $basePolicies,
                'defaultPolicies' => $defaultPolicies,
            ],
            'expectedCacheControl' => 'max-age=0, must-revalidate, no-cache, no-store, private',
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['CACHE_REWORK', 'v6.8.0.0'])]
    public function testStoreApiNoCacheRework(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set('_route', 'store-api.test');

        $this->cacheHeadersService->expects($this->never())
            ->method('applyCacheHash');

        $response = new Response();
        $this->subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    public function testSetResponseCacheAppliesHeaders(): void
    {
        // request without sales channel context should not apply headers
        $event = $this->createResponseEvent(new Request(), new Response());

        $this->cacheHeadersService->expects($this->once())
            ->method('applyCacheHeaders');

        $this->subscriber->setResponseCache($event);

        // request with sales channel context should apply headers
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, self::createMock(SalesChannelContext::class));
        $event = $this->createResponseEvent($request, new Response());

        $this->cacheHeadersService->expects($this->once())
            ->method('applyCacheHeaders');
        $this->subscriber->setResponseCache($event);
    }

    /**
     * @param array{header?: string, cookie?: string} $clientHash
     */
    #[DataProvider('cacheHashValidationProvider')]
    public function testCacheHashValidation(array $clientHash, ?string $serviceHash, bool $expectCacheable, bool $expectBypassHeader): void
    {
        $cacheHeadersService = $this->createMock(CacheHeadersService::class);

        $policyProvider = $this->createCachePolicyProvider(
            [
                'cacheable' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 100]]],
                'uncacheable' => ['headers' => ['cache_control' => ['private' => true, 'no_store' => true]]],
            ],
            [
                'storefront' => ['cacheable' => 'cacheable', 'uncacheable' => 'uncacheable'],
                'store_api' => ['cacheable' => 'cacheable', 'uncacheable' => 'uncacheable'],
            ],
        );

        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            null,
            null,
            $cacheHeadersService,
            $policyProvider,
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        if (isset($clientHash['header'])) {
            $request->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $clientHash['header']);
        }
        if (isset($clientHash['cookie'])) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $clientHash['cookie']);
        }

        if ($serviceHash !== null) {
            $eventMock = $this->createMock(HttpCacheCookieEvent::class);
            $eventMock->method('getHash')->willReturn($serviceHash);

            if ($serviceHash === HttpCacheCookieEvent::NOT_CACHEABLE) {
                $eventMock->method('shouldResponseBeCached')->willReturn(false);
            } else {
                $eventMock->method('shouldResponseBeCached')->willReturn(true);
            }
            $cacheHeadersService->expects($this->once())
                ->method('applyCacheHash')
                ->willReturn($eventMock);
        } else {
            $cacheHeadersService->expects($this->once())
                ->method('applyCacheHash')
                ->willReturn(null);
        }

        $response = new Response();
        $subscriber->setResponseCache(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        static::assertSame($expectBypassHeader, $response->headers->has(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS));

        if ($expectCacheable) {
            static::assertStringContainsString('public', (string) $response->headers->get('cache-control'));
        } else {
            static::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        }
    }

    /**
     * @return iterable<string, array{clientHash: array{header?: string, cookie?: string}, serviceHash: ?string, expectCacheable: bool, expectBypassHeader: bool}>
     */
    public static function cacheHashValidationProvider(): iterable
    {
        yield 'No client hash, null from service -> cacheable' => [
            'clientHash' => [],
            'serviceHash' => null,
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];

        yield 'Empty client cookie, null from service -> cacheable' => [
            'clientHash' => ['cookie' => ''],
            'serviceHash' => null,
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];

        yield 'Same hash in cookie and from service -> cacheable' => [
            'clientHash' => ['cookie' => 'abc123'],
            'serviceHash' => 'abc123',
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];

        yield 'Same hash in header and from service -> cacheable' => [
            'clientHash' => ['header' => 'abc123'],
            'serviceHash' => 'abc123',
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];

        yield 'Header takes precedence over cookie when matching' => [
            'clientHash' => ['header' => 'abc123', 'cookie' => 'different'],
            'serviceHash' => 'abc123',
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];

        yield 'NOT_CACHEABLE from service -> not cacheable with bypass header' => [
            'clientHash' => ['cookie' => HttpCacheCookieEvent::NOT_CACHEABLE],
            'serviceHash' => HttpCacheCookieEvent::NOT_CACHEABLE,
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];

        yield 'Hash mismatch -> not cacheable with bypass header' => [
            'clientHash' => ['header' => 'old-hash'],
            'serviceHash' => 'new-hash',
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];

        yield 'No client hash but service returns hash -> not cacheable with bypass header' => [
            'clientHash' => [],
            'serviceHash' => 'abc123',
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];
    }

    /**
     * @param array<string, CachePolicyConfig> $policiesConfig
     * @param array<string, string> $routePoliciesConfig
     * @param array<string, DefaultPoliciesConfig> $defaultPoliciesConfig
     */
    private function createCachePolicyProvider(
        array $policiesConfig = [],
        array $defaultPoliciesConfig = [],
        array $routePoliciesConfig = [],
    ): CachePolicyProvider {
        return CachePolicyProviderFactory::create($policiesConfig, $routePoliciesConfig, $defaultPoliciesConfig);
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

    private function getCacheResponseSubscriberWithCacheDisabled(): CacheResponseSubscriber
    {
        return new CacheResponseSubscriber(
            $this->cartService,
            100,
            false,
            new MaintenanceModeResolver($this->eventDispatcher),
            null,
            null,
            $this->createMock(CacheHeadersService::class),
            $this->createCachePolicyProvider(),
        );
    }
}
