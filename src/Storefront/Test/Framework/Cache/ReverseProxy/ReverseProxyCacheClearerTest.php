<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Storefront\Framework\Cache\ReverseProxy\RedisReverseProxyGateway;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer;

/**
 * @internal
 */
class ReverseProxyCacheClearerTest extends TestCase
{
    private MockHandler $mockHandler;

    private Client $client;

    private RedisReverseProxyGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $this->client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->gateway = new RedisReverseProxyGateway(
            ['http://localhost'],
            ['method' => 'BAN', 'headers' => []],
            ['method' => 'PURGE', 'headers' => ['foo' => '1'], 'urls' => ['/']],
            3,
            $this->createMock(\Redis::class),
            $this->client
        );
    }

    public function testClear(): void
    {
        $this->mockHandler->append(new Response(200));

        $this->gateway = new RedisReverseProxyGateway(
            ['http://localhost'],
            ['method' => 'BAN', 'headers' => []],
            ['method' => 'PURGE', 'headers' => [], 'urls' => ['/']],
            3,
            $this->createMock(\Redis::class),
            $this->client
        );

        $clearer = new ReverseProxyCacheClearer($this->gateway);
        $clearer->clear('noop');

        static::assertInstanceOf(RequestInterface::class, $this->mockHandler->getLastRequest());

        static::assertSame('PURGE', $this->mockHandler->getLastRequest()->getMethod());
        static::assertSame('/', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertFalse($this->mockHandler->getLastRequest()->hasHeader('foo'));
    }

    public function testClearWithHeader(): void
    {
        $this->mockHandler->append(new Response(200));

        $clearer = new ReverseProxyCacheClearer($this->gateway);
        $clearer->clear('noop');

        static::assertInstanceOf(RequestInterface::class, $this->mockHandler->getLastRequest());

        static::assertSame('PURGE', $this->mockHandler->getLastRequest()->getMethod());
        static::assertSame('/', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertTrue($this->mockHandler->getLastRequest()->hasHeader('foo'));
    }

    public function testClearWithException(): void
    {
        $this->mockHandler->append(new Response(500));

        $clearer = new ReverseProxyCacheClearer($this->gateway);

        static::expectException(\RuntimeException::class);
        $clearer->clear('noop');
    }

    public function testClearWithUnknownException(): void
    {
        $this->mockHandler->append(function (): void {
            throw new \RuntimeException('foo');
        });

        $clearer = new ReverseProxyCacheClearer($this->gateway);

        static::expectException(\RuntimeException::class);
        $clearer->clear('noop');
    }
}
