<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Move to core
 *
 * @covers \Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway
 */
class VarnishReverseProxyGatewayTest extends TestCase
{
    private Client $client;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $this->client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
    }

    public function testDecorated(): void
    {
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client, new NullLogger());

        static::expectException(DecorationPatternException::class);
        $gateway->getDecorated();
    }

    public function testTagggingMissingResponse(): void
    {
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client, new NullLogger());
        static::expectException(\ArgumentCountError::class);
        static::expectExceptionMessage('Too few arguments to function Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway::tag()');
        /** @phpstan-ignore-next-line  */
        $gateway->tag([], 'https://example.com');
    }

    public function testTagggingMissingResponseWithResponse(): void
    {
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client, new NullLogger());

        $response = new Response();

        $gateway->tag(['tag-1', 'tag-2'], 'https://example.com', $response);

        static::assertSame('tag-1 tag-2', $response->headers->get('xkey'));
    }

    public function testInvalidate(): void
    {
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client, new NullLogger());

        $this->mockHandler->append(new GuzzleResponse(200, [], ''));

        $gateway->invalidate(['tag-1', 'tag-2']);
        $gateway->flush();

        $request = $this->mockHandler->getLastRequest();
        static::assertNotNull($request);

        static::assertEquals('PURGE', $request->getMethod());
        static::assertEquals('http://localhost', $request->getUri()->__toString());
        static::assertEquals('tag-1 tag-2', $request->getHeader('xkey')[0]);
    }

    /**
     * @dataProvider providerExceptions
     */
    public function testInvalidateFails(\Throwable $e, string $message): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client, $logger);

        $this->mockHandler->append($e);

        $logger
            ->expects(static::once())
            ->method('critical')
            ->with('Error while flushing varnish cache', ['error' => $message, 'tags' => ['tag-1', 'tag-2']]);

        $gateway->invalidate(['tag-1', 'tag-2']);
        $gateway->flush();
    }

    public function testBan(): void
    {
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client, new NullLogger());

        $this->mockHandler->append(new GuzzleResponse(200, [], ''));

        $gateway->ban(['/']);

        $request = $this->mockHandler->getLastRequest();

        static::assertNotNull($request);

        static::assertEquals('PURGE', $request->getMethod());
        static::assertEquals('http://localhost/', $request->getUri()->__toString());
    }

    /**
     * @dataProvider providerExceptions
     */
    public function testBanFails(\Throwable $e, string $message): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client, $logger);

        $this->mockHandler->append($e);

        $logger
            ->expects(static::once())
            ->method('critical')
            ->with('Error while flushing varnish cache', ['error' => $message, 'urls' => ['/']]);

        $gateway->ban(['/']);
    }

    /**
     * @return array<string, array<\Throwable|string>>
     */
    public static function providerExceptions(): iterable
    {
        yield 'timed out' => [
            new ServerException('request timed out', new Request('GET', '/'), new GuzzleResponse(500)),
            'BAN request failed to / failed with error: request timed out',
        ];

        yield 'general php error' => [
            new TransferException('test'),
            'test',
        ];
    }
}
