<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(FastlyReverseProxyGateway::class)]
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
        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', '', 'http://localhost', new NullLogger());

        static::expectException(DecorationPatternException::class);
        $gateway->getDecorated();
    }

    public function testTag(): void
    {
        $resp = new Response();

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', '', 'http://localhost', new NullLogger());
        $gateway->tag(['foo', 'bla'], '', $resp);

        static::assertSame('foo bla', $resp->headers->get('surrogate-key'));
    }

    public function testTagWithInstanceTag(): void
    {
        $resp = new Response();

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'test', '0', 3, '', 'sw-1234', 'http://localhost', new NullLogger());
        $gateway->tag(['foo', 'bla'], '', $resp);

        static::assertSame('foo bla sw-1234', $resp->headers->get('surrogate-key'));
    }

    /**
     * @param string[] $tags
     */
    #[DataProvider('providerTags')]
    public function testInvalidate(array $tags, string $prefix = ''): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, $prefix, '', 'http://localhost', new NullLogger());
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

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost', new NullLogger());
        $tags = array_map('strval', range(1, 500));
        $gateway->invalidate($tags);
        $gateway->flush();

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
    public static function providerTags(): iterable
    {
        yield 'normal' => [['foo']];
        yield 'duplicate gets merged' => [['foo', 'foo']];
        yield 'is-prefixed' => [['foo', 'foo'], 'foo'];
    }

    public function testBanURL(): void
    {
        $this->mockHandler->append(new GuzzleResponse(200, []));

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost', new NullLogger());
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

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost', new NullLogger());
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

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', 'sw-1234', 'http://localhost', new NullLogger());
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

    #[DataProvider('providerExceptions')]
    public function testFastlyNotAvailable(\Throwable $e, string $message): void
    {
        $this->mockHandler->append($e);

        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->expects(static::once())
            ->method('critical')
            ->with('Error while flushing fastly cache', ['error' => $message, 'urls' => ['/']]);

        $gateway = new FastlyReverseProxyGateway($this->client, 'test', 'key', '0', 3, '', '', 'http://localhost', $logger);
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
