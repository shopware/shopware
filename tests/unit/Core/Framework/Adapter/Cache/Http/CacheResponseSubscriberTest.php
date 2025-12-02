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
use Shopware\Core\Framework\Adapter\Cache\Http\CacheHashService;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    private const IP = '127.0.0.1';

    private EventDispatcher $eventDispatcher;

    private CacheResponseSubscriber $subscriber;

    private CartService&MockObject $cartService;

    private CacheHashService&MockObject $cacheHashService;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->cartService = $this->createMock(CartService::class);
        $this->cacheHashService = $this->createMock(CacheHashService::class);

        $this->subscriber = new CacheResponseSubscriber(
            $this->cartService,
            100,
            true,
            new MaintenanceModeResolver($this->eventDispatcher),
            '5',
            '6',
            $this->cacheHashService
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
        // manually create instance with cache disabled
        $subscriber = new CacheResponseSubscriber(
            $this->cartService,
            100,
            false,
            new MaintenanceModeResolver($this->eventDispatcher),
            null,
            null,
            $this->createMock(CacheHashService::class)
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
        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->setResponseCacheHeader($event);

        static::assertSame('1', $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderCacheDisabled(): void
    {
        // manually create instance with cache disabled
        $subscriber = new CacheResponseSubscriber(
            $this->cartService,
            100,
            false,
            new MaintenanceModeResolver($this->eventDispatcher),
            null,
            null,
            $this->createMock(CacheHashService::class)
        );

        $request = new Request();
        $request->attributes->add([PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);

        $response = new Response();

        $event = $this->createResponseEvent($request, $response);

        $this->cacheHashService->expects($this->never())
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

        $this->cacheHashService->expects($this->never())
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
            $this->cacheHashService->expects($this->once())
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

    #[DataProvider('notCacheableRequestProvider')]
    public function testNotCacheablePages(Request $request, bool $cacheHashExpected): void
    {
        $response = new Response();

        $this->cacheHashService->expects($cacheHashExpected ? $this->once() : $this->never())
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

    /**
     * @return iterable<string, array{0: Request, 1: bool}>
     */
    public static function notCacheableRequestProvider(): iterable
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign(['customer' => null]);

        $postRequest = new Request([], [], [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext]);
        $postRequest->setMethod(Request::METHOD_POST);

        yield 'admin request' => [new Request([], [], ['_route' => 'admin.dashboard.index']), false];
        yield 'post request' => [$postRequest, true];
    }

    #[DataProvider('cookiesUntouchedProvider')]
    public function testCookiesAreUntouched(Request $request, ?Response $response = null): void
    {
        if (!$response) {
            $response = new Response();
        }

        $this->cacheHashService->expects($this->never())
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
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, [
            'states' => ['cart-filled'],
        ]);
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

    public function testMakeGetsCached(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));
        $request->cookies->set(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE, 'cart-filled');

        $this->cacheHashService->expects($this->once())
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
