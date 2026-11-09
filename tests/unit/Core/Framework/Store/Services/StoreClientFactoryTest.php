<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\MiddlewareInterface;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreClientFactory::class)]
class StoreClientFactoryTest extends TestCase
{
    public function testCreatedClientSendsRequestsToTheConfiguredStoreApi(): void
    {
        $recorder = new RecordingMiddleware();

        $factory = new StoreClientFactory(new StaticSystemConfigService(['core.store.apiUri' => 'http://shopware.swag']));
        $response = $factory->create([$recorder])->request('GET', '/swplatform/licenses');

        static::assertSame(204, $response->getStatusCode());
        static::assertCount(1, $recorder->requests);

        $request = $recorder->requests[0];
        static::assertSame('http://shopware.swag/swplatform/licenses', (string) $request->getUri());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('application/vnd.api+json,application/json', $request->getHeaderLine('Accept'));
    }

    public function testCreatedClientRunsEveryGivenMiddlewareInOrder(): void
    {
        $first = new TaggingMiddleware('first');
        $second = new TaggingMiddleware('second');
        $recorder = new RecordingMiddleware();

        $factory = new StoreClientFactory(new StaticSystemConfigService(['core.store.apiUri' => 'http://shopware.swag']));
        // The first given middleware is the outermost one, so the recorder given last sees the tags of both others
        $factory->create([$first, $second, $recorder])->request('GET', '/');

        static::assertCount(1, $recorder->requests);
        static::assertSame(['first', 'second'], $recorder->requests[0]->getHeader('X-Tag'));
    }
}

/**
 * Answers every request itself and records it, so no request leaves the test.
 *
 * @internal
 */
class RecordingMiddleware implements MiddlewareInterface
{
    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request): PromiseInterface {
            $this->requests[] = $request;

            return Create::promiseFor(new Response(204));
        };
    }
}

/**
 * @internal
 */
class TaggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $tag)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            return $handler($request->withAddedHeader('X-Tag', $this->tag), $options);
        };
    }
}
