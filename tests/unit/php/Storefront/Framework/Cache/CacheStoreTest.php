<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\CacheStateValidator;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\HttpCacheKeyGenerator;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\CacheStore
 */
class CacheStoreTest extends TestCase
{
    private const IP = '127.0.0.1';

    /**
     * @dataProvider maintenanceRequest
     *
     * @param string[] $whitelist
     */
    public function testMaintenanceRequest(bool $active, array $whitelist, bool $shouldBeCached): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $active);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, \json_encode($whitelist));
        $request->server->set('REMOTE_ADDR', self::IP);
        static::assertSame(self::IP, $request->getClientIp());

        $item = new CacheItem();

        $count = $shouldBeCached ? 1 : 0;

        $cache = $this->createMock(TagAwareAdapter::class);
        $cache->expects(static::exactly($count))
            ->method('getItem')
            ->willReturn($item);

        // ensure empty request stack
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $maintenanceModeResolver = new MaintenanceModeResolver($requestStack);

        $cacheKeyGenerator = $this->createMock(HttpCacheKeyGenerator::class);
        $cacheKeyGenerator->method('generate')->willReturn('key');

        $store = new CacheStore(
            $cache,
            new CacheStateValidator([]),
            $this->createMock(EventDispatcher::class),
            $this->createMock(CacheTracer::class),
            $cacheKeyGenerator,
            $maintenanceModeResolver,
            []
        );

        $store->lookup($request);
    }

    /**
     * @return array<string, array{0: boolean, 1: string[], 2: boolean}>
     */
    public function maintenanceRequest(): iterable
    {
        yield 'Always cache requests when maintenance is inactive' => [false, [], true];
        yield 'Always cache requests when maintenance is active' => [true, [], true];
        yield 'Do not cache requests of whitelisted ip' => [true, [self::IP], false];
        yield 'Cache requests if ip is not whitelisted' => [true, ['120.0.0.0'], true];
    }

    public function testSessionIsNotCached(): void
    {
        $cacheKeyGenerator = $this->createMock(HttpCacheKeyGenerator::class);
        $cacheKeyGenerator->method('generate')->willReturn('key');
        $requestStack = new RequestStack();
        $maintenanceModeResolver = new MaintenanceModeResolver($requestStack);

        $store = new CacheStore(
            new TagAwareAdapter(new ArrayAdapter()),
            new CacheStateValidator([]),
            $this->createMock(EventDispatcher::class),
            new CacheTracer($this->createMock(SystemConfigService::class), $this->createMock(Translator::class), new CacheTagCollection()),
            $cacheKeyGenerator,
            $maintenanceModeResolver,
            []
        );

        $request = new Request();
        $response = new Response();
        $response->setPublic();
        $response->headers->setCookie(new Cookie('session-', 'bla'));
        $response->headers->setCookie(new Cookie('bla', 'val'));

        $store->write($request, $response);

        $cachedResponse = $store->lookup($request);
        static::assertNotNull($cachedResponse);

        static::assertCount(1, $cachedResponse->headers->getCookies());
        $cookie = $cachedResponse->headers->getCookies()[0];

        static::assertSame('bla', $cookie->getName());
        static::assertSame('val', $cookie->getValue());
    }
}
