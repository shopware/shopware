<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ReverseProxyCache::class)]
class ReverseProxyCacheTest extends TestCase
{
    public function testFlushIsCalledInDestruct(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway->expects(static::once())->method('flush');

        $cache = new ReverseProxyCache($gateway, $this->createMock(AbstractCacheTracer::class), [], $this->createMock(CacheTagCollector::class));

        // this is the only way to call the destructor
        unset($cache);
    }

    public function testTagsFromResponseGetsMergedAndRemoved(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway
            ->expects(static::once())
            ->method('tag')
            ->with(['foo']);

        $cache = new ReverseProxyCache($gateway, $this->createMock(AbstractCacheTracer::class), [], $this->createMock(CacheTagCollector::class));

        $response = new Response();
        $response->headers->set(CacheStore::TAG_HEADER, '["foo"]');

        $request = new Request();
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, 'test');
        $cache->write($request, $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    /**
     * The store is only used to track the cache tags and not to cache actual
     */
    public function testLookup(): void
    {
        $store = new ReverseProxyCache($this->createMock(AbstractReverseProxyGateway::class), $this->createMock(CacheTracer::class), [], $this->createMock(CacheTagCollector::class));
        static::assertNull($store->lookup(new Request()));
        static::assertFalse($store->isLocked(new Request()));
        static::assertTrue($store->lock(new Request()));
        static::assertTrue($store->unlock(new Request()));
        $store->cleanup();
    }

    public function testWriteAddsGlobalStates(): void
    {
        $store = new ReverseProxyCache(
            $this->createMock(AbstractReverseProxyGateway::class),
            $this->createMock(CacheTracer::class),
            [CacheResponseSubscriber::STATE_LOGGED_IN],
            $this->createMock(CacheTagCollector::class)
        );

        $request = new Request();
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, '/foo');
        $response = new Response();
        $store->write($request, $response);

        static::assertTrue($response->headers->has(CacheResponseSubscriber::INVALIDATION_STATES_HEADER));
        static::assertSame($response->headers->get(CacheResponseSubscriber::INVALIDATION_STATES_HEADER), CacheResponseSubscriber::STATE_LOGGED_IN);
    }

    public function testPurge(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())->method('ban')->with(['/foo']);
        $store = new ReverseProxyCache($gateway, $this->createMock(CacheTracer::class), [], $this->createMock(CacheTagCollector::class));

        $store->purge('/foo');
    }

    public function testInvalidateWithoutOriginalUrl(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::never())->method('ban');
        $store = new ReverseProxyCache($gateway, $this->createMock(CacheTracer::class), [], $this->createMock(CacheTagCollector::class));
        $store->invalidate(new Request());
    }

    public function testTaggingOfRequest(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);

        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())->method('tag')->with(['product-1', 'category-1'], '/');

        $tracer = $this->createMock(CacheTracer::class);
        $tracer->expects(static::once())->method('get')->willReturn(['theme-config-1', 'system-config-1', 'product-1', 'category-1']);

        $store = new ReverseProxyCache($gateway, $tracer, [], $this->createMock(CacheTagCollector::class));

        $request = new Request();
        $store->write($request, new Response());
    }

    public function testInvoke(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects(static::once())->method('invalidate')->with(['foo']);
        $store = new ReverseProxyCache($gateway, $this->createMock(CacheTracer::class), [], $this->createMock(CacheTagCollector::class));
        $store(new InvalidateCacheEvent(['foo']));
    }
}
