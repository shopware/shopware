<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCacheClearer;

class ReverseProxyCacheClearerTest extends TestCase
{
    private MockHandler $mockHandler;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $this->client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
    }

    public function testClear(): void
    {
        $this->mockHandler->append(new Response(200));

        $clearer = new ReverseProxyCacheClearer($this->client, ['http://localhost'], 'PURGE', [], ['/'], 1);
        $clearer->clear('noop');

        static::assertSame('PURGE', $this->mockHandler->getLastRequest()->getMethod());
        static::assertSame('/', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertFalse($this->mockHandler->getLastRequest()->hasHeader('foo'));
    }

    public function testClearWithHeader(): void
    {
        $this->mockHandler->append(new Response(200));

        $clearer = new ReverseProxyCacheClearer($this->client, ['http://localhost'], 'PURGE', ['foo' => 1], ['/'], 1);
        $clearer->clear('noop');

        static::assertSame('PURGE', $this->mockHandler->getLastRequest()->getMethod());
        static::assertSame('/', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertTrue($this->mockHandler->getLastRequest()->hasHeader('foo'));
    }

    public function testClearWithException(): void
    {
        $this->mockHandler->append(new Response(500));

        $clearer = new ReverseProxyCacheClearer($this->client, ['http://localhost'], 'PURGE', ['foo' => 1], ['/'], 1);

        static::expectException(\RuntimeException::class);
        $clearer->clear('noop');
    }

    public function testClearWithUnknownException(): void
    {
        $this->mockHandler->append(function (): void {
            throw new \RuntimeException('foo');
        });

        $clearer = new ReverseProxyCacheClearer($this->client, ['http://localhost'], 'PURGE', ['foo' => 1], ['/'], 1);

        static::expectException(\RuntimeException::class);
        $clearer->clear('noop');
    }
}
