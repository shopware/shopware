<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\ReverseProxy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache
 */
class ReverseProxyCacheTest extends TestCase
{
    public function testFlushIsCalledInDestruct(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway->expects(static::once())->method('flush');

        $cache = new ReverseProxyCache($gateway, $this->createMock(AbstractCacheTracer::class), []);

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

        $cache = new ReverseProxyCache($gateway, $this->createMock(AbstractCacheTracer::class), []);

        $response = new Response();
        $response->headers->set(CacheStore::TAG_HEADER, '["foo"]');

        $request = new Request();
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, 'test');
        $cache->write($request, $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }
}
