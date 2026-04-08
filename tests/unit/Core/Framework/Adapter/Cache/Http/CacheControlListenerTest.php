<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlListener;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - This test is deprecated because the CacheControlListener is deprecated.
 */
#[CoversClass(CacheControlListener::class)]
class CacheControlListenerTest extends TestCase
{
    #[DataProvider('headerCases')]
    #[DisabledFeatures(['v6.8.0.0', 'PERFORMANCE_TWEAKS', 'CACHE_REWORK'])]
    public function testResponseHeadersDeprecated(bool $reverseProxyEnabled, ?string $beforeHeader, string $afterHeader): void
    {
        $response = new Response();
        $response->headers->set(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER, 'foo');

        if ($beforeHeader) {
            $response->headers->set('cache-control', $beforeHeader);
        }

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $subscriber = new CacheControlListener($reverseProxyEnabled, $eventDispatcher);

        $subscriber->__invoke(new BeforeSendResponseEvent(new Request(), $response));

        static::assertSame($afterHeader, $response->headers->get('cache-control'));

        if (!$reverseProxyEnabled) {
            static::assertFalse($response->headers->has(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER));
        }
    }

    #[DataProvider('headerCases')]
    public function testResponseHeaders(bool $reverseProxyEnabled, ?string $beforeHeader, string $afterHeader): void
    {
        $response = new Response();

        if ($beforeHeader) {
            $response->headers->set('cache-control', $beforeHeader);
        }

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $subscriber = new CacheControlListener($reverseProxyEnabled, $eventDispatcher);

        $subscriber->__invoke(new BeforeSendResponseEvent(new Request(), $response));

        static::assertSame($afterHeader, $response->headers->get('cache-control'));
    }

    /**
     * @return iterable<string, array<int, bool|string|null>>
     */
    public static function headerCases(): iterable
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

    public function testHeadersNotModified(): void
    {
        $response = new Response();
        $response->headers->set('cache-control', 'public, s-maxage=64000');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $subscriber = new CacheControlListener(false, $eventDispatcher);

        // StoreAPI
        $storeApiRequest = new Request();
        $storeApiRequest->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $subscriber->__invoke(new BeforeSendResponseEvent($storeApiRequest, $response));
        static::assertSame('public, s-maxage=64000', $response->headers->get('cache-control'));

        // Storefront
        $storefrontRequest = new Request();
        $storefrontRequest->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        $subscriber->__invoke(new BeforeSendResponseEvent($storefrontRequest, $response));
        static::assertSame('public, s-maxage=64000', $response->headers->get('cache-control'));
    }

    #[DisabledFeatures(['CACHE_REWORK', 'v6.8.0.0'])]
    public function testStoreApiHeadersWithoutFeatureFlags(): void
    {
        $response = new Response();
        $response->headers->set('cache-control', 'public, s-maxage=64000');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $subscriber = new CacheControlListener(false, $eventDispatcher);
        $subscriber->__invoke(new BeforeSendResponseEvent(new Request(), $response));

        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    #[DataProvider('administrationHeadersCases')]
    public function testAdministrationHeadersNotModified(Request $request, Response $response, string $expectedCacheControl, ?string $expectedCacheIdHeader = null): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function ($event) {
            if ($event instanceof BeforeCacheControlEvent) {
                $administrationListener = new AdministrationCacheControlListener();
                $administrationListener->__invoke($event);
            }

            return $event;
        });
        $subscriber = new CacheControlListener(false, $eventDispatcher);

        $subscriber->__invoke(new BeforeSendResponseEvent($request, $response));

        static::assertSame($expectedCacheControl, $response->headers->get('cache-control'));
        static::assertSame($expectedCacheIdHeader, $response->headers->get(AdministrationController::CACHE_ID_HEADER));
    }

    /**
     * @return iterable<string, array{request: Request, response: Response, expectedCacheControl: string, expectedCacheIdHeader: string|null}>
     */
    public static function administrationHeadersCases(): iterable
    {
        yield 'administration route scope' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]]
            ),
            'response' => new Response('', 200, [
                'cache-control' => 'max-age=0, public, stale-while-revalidate=86400',
            ]),
            'expectedCacheControl' => 'max-age=0, public, stale-while-revalidate=86400',
            'expectedCacheIdHeader' => null,
        ];

        yield 'administration route name' => [
            'request' => new Request(
                attributes: ['_route' => 'administration.index']
            ),
            'response' => new Response('', 200, [
                'cache-control' => 'max-age=0, public, stale-while-revalidate=86400',
            ]),
            'expectedCacheControl' => 'max-age=0, public, stale-while-revalidate=86400',
            'expectedCacheIdHeader' => null,
        ];

        yield 'administration cache ID marker' => [
            'request' => new Request(),
            'response' => new Response('', 200, [
                'cache-control' => 'max-age=0, public, stale-while-revalidate=86400',
                AdministrationController::CACHE_ID_HEADER => AdministrationController::CACHE_ID_ADMINISTRATION,
            ]),
            'expectedCacheControl' => 'max-age=0, public, stale-while-revalidate=86400',
            'expectedCacheIdHeader' => AdministrationController::CACHE_ID_ADMINISTRATION,
        ];
    }

    public function testNonAdministrationHeadersAreModified(): void
    {
        $response = new Response();
        $response->headers->set('cache-control', 'max-age=0, public, stale-while-revalidate=86400');

        $request = new Request();
        // No administration markers set

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $subscriber = new CacheControlListener(false, $eventDispatcher);

        $subscriber->__invoke(new BeforeSendResponseEvent($request, $response));

        // Should be modified to no-cache, private for non-administration routes
        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }
}
