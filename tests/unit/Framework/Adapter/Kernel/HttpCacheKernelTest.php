<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework\Adapter\Kernel;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel
 */
class HttpCacheKernelTest extends TestCase
{
    public function testOnOldKernelSkips(): void
    {
        $before = KernelFactory::$active;
        KernelFactory::$active = false;

        $core = $this->createMock(StoreInterface::class);
        $core->expects(static::never())->method('lookup');
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);

        KernelFactory::$active = $before;
    }

    public function testNoCacheAvailable(): void
    {
        $core = $this->createMock(StoreInterface::class);
        $core->expects(static::once())->method('lookup');
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
    }

    public function testOnNewKernelWithCachedResponse(): void
    {
        $core = $this->createMock(StoreInterface::class);
        $cachedResponse = new Response('cached', 200, ['s-maxage' => 3600]);
        $cachedResponse->setPublic();

        $core->expects(static::once())->method('lookup')->willReturn($cachedResponse);
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $response = $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
        static::assertSame('cached', (string) $response->getContent());
    }

    public function testMaintenanceRequestMatches(): void
    {
        $core = $this->createMock(StoreInterface::class);
        $cachedResponse = new Response('cached', 200, ['s-maxage' => 3600]);
        $cachedResponse->setPublic();
        $cachedResponse->headers->set(HttpCacheKernel::MAINTENANCE_WHITELIST_HEADER, '1.1.1.1');

        $core->expects(static::once())->method('lookup')->willReturn($cachedResponse);
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '1.1.1.1');

        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        // As we match to the maintenance whitelist, we should not get the cached response
        static::assertNotEquals('cached', (string) $response->getContent());
    }

    public function testMaintenanceRequestNotMatches(): void
    {
        $core = $this->createMock(StoreInterface::class);
        $cachedResponse = new Response('cached', 200, ['s-maxage' => 3600]);
        $cachedResponse->setPublic();
        $cachedResponse->headers->set(HttpCacheKernel::MAINTENANCE_WHITELIST_HEADER, '1.1.1.1');

        $core->expects(static::once())->method('lookup')->willReturn($cachedResponse);
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $response = $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);

        // As we match to the maintenance whitelist, we should not get the cached response
        static::assertEquals('cached', (string) $response->getContent());
    }

    /**
     * We cannot call the Symfony HttpKernel component, when we use an external reverse proxy which is able to use ESI tags
     * The Symfony HttpCache component would resolve the ESI <esi:include> tags in the HTML and won't pass them to the external reverse proxy
     */
    public function testInternalHttpCacheGetsSkippedOnReverseProxy(): void
    {
        $core = $this->createMock(StoreInterface::class);
        $core->expects(static::never())->method('lookup');

        $core->expects(static::once())->method('write');

        $kernel = $this->getHttpCacheKernel($core, true);

        $kernel->handle(new Request(), HttpKernelInterface::MAIN_REQUEST, false);
    }

    /**
     * @dataProvider providerSkipCaching
     */
    public function testSubRequestSkipsCache(Request $request, int $type): void
    {
        $core = $this->createMock(StoreInterface::class);
        $core->expects(static::never())->method('lookup');
        $core->expects(static::never())->method('write');

        $kernel = $this->getHttpCacheKernel($core);

        $kernel->handle($request, $type, false);
    }

    public static function providerSkipCaching(): \Generator
    {
        yield 'post request' => [
            new Request(server: ['REQUEST_METHOD' => 'POST']),
            HttpKernelInterface::MAIN_REQUEST,
        ];

        yield 'sub request' => [
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
        ];
    }

    public function getInnerKernel(): HttpKernelInterface&MockObject
    {
        $inner = $this->createMock(HttpKernelInterface::class);
        $inner
            ->method('handle')
            ->willReturn(new Response());

        return $inner;
    }

    public function getHttpCacheKernel(StoreInterface&MockObject $core, bool $reverseProxyEnabled = false): HttpCacheKernel
    {
        return new HttpCacheKernel(
            $this->getInnerKernel(),
            null,
            new Esi(),
            [],
            new EventDispatcher(),
            $reverseProxyEnabled,
            $core
        );
    }
}
