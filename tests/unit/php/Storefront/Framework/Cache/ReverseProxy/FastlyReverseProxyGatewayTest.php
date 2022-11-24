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
use Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Shopware\Storefront\Framework\Cache\ReverseProxy\FastlyReverseProxyGateway
 *
 * @internal
 */
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

    public function testDecoration(): void
    {
        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', '', 'http://localhost');

        static::expectException(DecorationPatternException::class);
        $gateway->getDecorated();
    }

    public function testTagDeprecated(): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Parameter $response is required for FastlyReverseProxyGateway');

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', '', 'http://localhost');
        $gateway->tag(['foo', 'bla'], '');
    }

    public function testTag(): void
    {
        $resp = new Response();

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', '', 'http://localhost');
        $gateway->tag(['foo', 'bla'], '', $resp);

        static::assertSame('foo bla', $resp->headers->get('surrogate-key'));
    }

    public function testTagWithInstanceTag(): void
    {
        $resp = new Response();

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', 'sw-1234', 'http://localhost');
        $gateway->tag(['foo', 'bla'], '', $resp);

        static::assertSame('foo bla sw-1234', $resp->headers->get('surrogate-key'));
    }

    /**
     * @param string[] $tags
     *
     * @dataProvider providerTags
     */
    public function testInvalidate(array $tags, string $prefix = ''): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, $prefix, '', 'http://localhost');
        $gateway->invalidate($tags);
        $gateway->flush();

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('/service/test/purge', $lastRequest->getRequestTarget());
        static::assertSame([$prefix . 'foo'], $lastRequest->getHeader('surrogate-key'));
        static::assertSame(['key'], $lastRequest->getHeader('Fastly-Key'));

        static::assertCount(0, $this->mockHandler);
    }

    public function testInvalidateGoesBeyondLimitOfApi(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost');
        $tags = array_map('strval', range(1, 500));
        $gateway->invalidate($tags);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('/service/test/purge', $lastRequest->getRequestTarget());
        static::assertSame([implode(' ', range(257, 500))], $lastRequest->getHeader('surrogate-key'));
        static::assertSame(['key'], $lastRequest->getHeader('Fastly-Key'));

        static::assertCount(0, $this->mockHandler);
    }

    /**
     * @return array<string, array<int, string|string[]>>
     */
    public function providerTags(): iterable
    {
        yield 'normal' => [['foo']];
        yield 'duplicate gets merged' => [['foo', 'foo']];
        yield 'is-prefixed' => [['foo', 'foo'], 'foo'];
    }

    public function testBanURL(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost');
        $gateway->ban(['/foo']);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('/purge/localhost/foo', $lastRequest->getRequestTarget());
        static::assertSame('POST', $lastRequest->getMethod());
        static::assertFalse($lastRequest->hasHeader('surrogate-key'));
        static::assertSame(['key'], $lastRequest->getHeader('Fastly-Key'));
    }

    public function testBanAll(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost');
        $gateway->banAll();

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('/service/test/purge_all', $lastRequest->getRequestTarget());
        static::assertSame('POST', $lastRequest->getMethod());
        static::assertFalse($lastRequest->hasHeader('surrogate-key'));
        static::assertSame(['key'], $lastRequest->getHeader('Fastly-Key'));
    }

    public function testBanAllWithInstanceTag(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', 'sw-1234', 'http://localhost');
        $gateway->banAll();
        $gateway->flush();

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('/service/test/purge', $lastRequest->getRequestTarget());
        static::assertSame('POST', $lastRequest->getMethod());
        static::assertTrue($lastRequest->hasHeader('surrogate-key'));
        static::assertSame(['sw-1234'], $lastRequest->getHeader('surrogate-key'));

        static::assertSame(['key'], $lastRequest->getHeader('Fastly-Key'));
    }

    /**
     * @dataProvider providerExceptions
     */
    public function testFastlyNotAvailable(\Throwable $e, string $message): void
    {
        $this->mockHandler->append($e);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage($message);

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost');
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
