<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\ReverseProxy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Move to core
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

    /**
     * @dataProvider writeCases
     */
    public function testWrite(bool $active, string $attributeUrl, string $pathDetail, string $expected): void
    {
        $before = KernelFactory::$active;
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway
            ->expects(static::once())
            ->method('tag')
            ->with([], $expected);

        $cache = new ReverseProxyCache($gateway, $this->createMock(AbstractCacheTracer::class), []);

        $request = Request::create('http://localhost' . $pathDetail);
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, $attributeUrl);

        KernelFactory::$active = $active;

        $cache->write($request, new Response(''));

        KernelFactory::$active = $before;
    }

    public static function writeCases(): \Generator
    {
        yield 'old kernel way, real url attribute' => [
            'active' => false,
            'attributeUrl' => '/fooo',
            'pathDetail' => '/detail/12345',
            'expected' => '/fooo',
        ];

        yield 'new kernel way, uses pathInfo' => [
            'active' => true,
            'attributeUrl' => '/wrong-not-used',
            'pathDetail' => '/fooo',
            'expected' => '/fooo',
        ];
    }
}
