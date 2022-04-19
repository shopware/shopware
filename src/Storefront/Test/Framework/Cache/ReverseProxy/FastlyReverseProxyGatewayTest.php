<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Symfony\Component\HttpFoundation\Response;
use function array_fill;

class FastlyReverseProxyGatewayTest extends TestCase
{
    private Client $client;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $this->client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
    }

    public function testTag(): void
    {
        $resp = new Response();

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '');
        $gateway->tag(['foo', 'bla'], '', $resp);

        static::assertSame('foo bla', $resp->headers->get('surrogate-key'));
    }

    public function testInvalidate(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '');
        $gateway->invalidate(array_fill(0, 257, 'foo'));

        static::assertSame('/service/test/purge', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertSame(['foo'], $this->mockHandler->getLastRequest()->getHeader('surrogate-key'));
        static::assertSame(['key'], $this->mockHandler->getLastRequest()->getHeader('Fastly-Key'));
    }

    public function testBanURL(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '');
        $gateway->ban(['/']);

        static::assertSame('/', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertSame('PURGE', $this->mockHandler->getLastRequest()->getMethod());
        static::assertFalse($this->mockHandler->getLastRequest()->hasHeader('surrogate-key'));
        static::assertSame(['key'], $this->mockHandler->getLastRequest()->getHeader('Fastly-Key'));
    }

    public function testBanAll(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '');
        $gateway->banAll(['/']);

        static::assertSame('/service/test/purge_all', $this->mockHandler->getLastRequest()->getRequestTarget());
        static::assertSame('POST', $this->mockHandler->getLastRequest()->getMethod());
        static::assertFalse($this->mockHandler->getLastRequest()->hasHeader('surrogate-key'));
        static::assertSame(['key'], $this->mockHandler->getLastRequest()->getHeader('Fastly-Key'));
    }
}
