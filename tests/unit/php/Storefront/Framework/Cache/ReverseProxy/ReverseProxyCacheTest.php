<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\ReverseProxy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache;

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
}
