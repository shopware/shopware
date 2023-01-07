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
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
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
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client);

        static::expectException(DecorationPatternException::class);
        $gateway->getDecorated();
    }

    public function testTagggingMissingResponse(): void
    {
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client);
        static::expectException(\ArgumentCountError::class);
        static::expectExceptionMessage('Too few arguments to function Shopware\Storefront\Framework\Cache\ReverseProxy\VarnishReverseProxyGateway::tag()');
        /** @phpstan-ignore-next-line  */
        $gateway->tag([], 'https://example.com');
    }

    public function testTagggingMissingResponseWithResponse(): void
    {
        $gateway = new VarnishReverseProxyGateway([], 0, $this->client);

        $response = new Response();

        $gateway->tag(['tag-1', 'tag-2'], 'https://example.com', $response);

        static::assertSame('tag-1 tag-2', $response->headers->get('xkey'));
    }

    public function testInvalidate(): void
    {
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client);

        $this->mockHandler->append(new GuzzleResponse(200, [], ''));

        $gateway->invalidate(['tag-1', 'tag-2']);

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
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client);

        $this->mockHandler->append($e);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage($message);

        $gateway->invalidate(['tag-1', 'tag-2']);
    }

    public function testBan(): void
    {
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client);

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
        $gateway = new VarnishReverseProxyGateway(['http://localhost'], 0, $this->client);

        $this->mockHandler->append($e);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage($message);

        $gateway->ban(['/']);
    }

    /**
     * @return array<string, array<\Throwable|string>>
     */
    public function providerExceptions(): iterable
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
