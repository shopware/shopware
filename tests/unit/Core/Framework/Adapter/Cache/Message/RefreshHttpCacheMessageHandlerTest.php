<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessageHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(RefreshHttpCacheMessage::class)]
class RefreshHttpCacheMessageHandlerTest extends TestCase
{
    private HttpKernelInterface&MockObject $kernel;

    private StoreInterface&MockObject $store;

    private CacheInterface&MockObject $cache;

    private RefreshHttpCacheMessageHandler $handler;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(HttpKernelInterface::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->handler = new RefreshHttpCacheMessageHandler($this->kernel, $this->store, $this->cache);
    }

    public function testInvokeHandlesMessage(): void
    {
        $message = new RefreshHttpCacheMessage(
            'lock-key',
            ['query' => 'value'],
            ['attribute' => 'value'],
            ['cookie' => 'value'],
            ['HTTP_HOST' => 'example.com'],
            ['127.0.0.1'],
            Request::HEADER_FORWARDED
        );

        $response = new Response('test content');

        $this->kernel->expects($this->once())
            ->method('handle')
            ->with(
                static::callback(function (Request $request) {
                    return $request->query->get('query') === 'value'
                        && $request->attributes->get('attribute') === 'value'
                        && $request->cookies->get('cookie') === 'value'
                        && $request->server->get('HTTP_HOST') === 'example.com'
                        && $request->hasSession();
                }),
                HttpKernelInterface::MAIN_REQUEST,
                false
            )
            ->willReturn($response);

        $this->store->expects($this->once())
            ->method('write')
            ->with(
                static::isInstanceOf(Request::class),
                static::identicalTo($response)
            );

        $this->cache->expects($this->once())
            ->method('delete')
            ->with('lock-key');

        ($this->handler)($message);
    }

    public function testInvokeSetsTrustedProxies(): void
    {
        $originalTrustedIps = Request::getTrustedProxies();
        $originalTrustedHeaders = Request::getTrustedHeaderSet();

        $message = new RefreshHttpCacheMessage(
            'lock-key',
            [],
            [],
            [],
            [],
            ['192.168.1.1', '10.0.0.1'],
            Request::HEADER_X_FORWARDED_FOR
        );

        $this->kernel->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () {
                static::assertSame(['192.168.1.1', '10.0.0.1'], Request::getTrustedProxies());
                static::assertSame(Request::HEADER_X_FORWARDED_FOR, Request::getTrustedHeaderSet());

                return new Response();
            });

        $this->store->expects($this->once())->method('write');
        $this->cache->expects($this->once())->method('delete');

        ($this->handler)($message);

        static::assertSame($originalTrustedIps, Request::getTrustedProxies());
        static::assertSame($originalTrustedHeaders, Request::getTrustedHeaderSet());
    }

    public function testInvokeRestoresTrustedProxiesOnException(): void
    {
        $originalTrustedIps = Request::getTrustedProxies();
        $originalTrustedHeaders = Request::getTrustedHeaderSet();

        $message = new RefreshHttpCacheMessage(
            'lock-key',
            [],
            [],
            [],
            [],
            ['192.168.1.1'],
            Request::HEADER_X_FORWARDED_FOR
        );

        $this->kernel->expects($this->once())
            ->method('handle')
            ->willThrowException(new \Exception('Kernel error'));

        $this->store->expects($this->never())->method('write');
        $this->cache->expects($this->never())->method('delete');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Kernel error');

        try {
            ($this->handler)($message);
        } finally {
            static::assertSame($originalTrustedIps, Request::getTrustedProxies());
            static::assertSame($originalTrustedHeaders, Request::getTrustedHeaderSet());
        }
    }

    public function testInvokeCreatesRequestWithSession(): void
    {
        $message = new RefreshHttpCacheMessage('lock-key');
        $response = new Response();

        $this->kernel->expects($this->once())
            ->method('handle')
            ->with(
                static::callback(function (Request $request) {
                    return $request->hasSession()
                        && $request->getSession() instanceof Session;
                }),
                HttpKernelInterface::MAIN_REQUEST,
                false
            )
            ->willReturn($response);

        $this->store->expects($this->once())->method('write');
        $this->cache->expects($this->once())->method('delete')->with('lock-key');

        ($this->handler)($message);
    }
}
