<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\CompressedCriteriaRequestListener;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Base64;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompressedCriteriaRequestListener::class)]
class CompressedCriteriaRequestListenerTest extends TestCase
{
    private CompressedCriteriaRequestListener $listener;

    protected function setUp(): void
    {
        $this->listener = new CompressedCriteriaRequestListener(new CompressedCriteriaDecoder());
    }

    public function testRunsAfterTheAuthenticationAndBeforeTheContextIsResolved(): void
    {
        static::assertSame(
            [
                KernelEvents::CONTROLLER => [
                    'expandCompressedCriteria',
                    KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_POST,
                ],
            ],
            CompressedCriteriaRequestListener::getSubscribedEvents()
        );
    }

    public function testFieldsOfTheCompressedCriteriaAreReadableAsQueryParameters(): void
    {
        $request = self::createStoreApiRequest([
            '_criteria' => self::compress([
                'limit' => 2,
                'slots' => 'slot-a|slot-b',
                'buildTree' => false,
                'includes' => ['category' => ['id', 'name']],
            ]),
        ]);

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame(2, $request->query->getInt('limit'));
        static::assertSame('slot-a|slot-b', $request->query->get('slots'));
        static::assertFalse($request->query->getBoolean('buildTree', true));
        static::assertSame(['category' => ['id', 'name']], $request->query->all('includes'));
    }

    public function testCompressedCriteriaWinsOverPlainQueryParameter(): void
    {
        $request = self::createStoreApiRequest([
            'limit' => '10',
            'p' => '3',
            '_criteria' => self::compress(['limit' => 2]),
        ]);

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame(2, $request->query->getInt('limit'));
        static::assertSame(3, $request->query->getInt('p'), 'A plain parameter that is not part of the compressed criteria is kept');
    }

    public function testCompressedParameterIsKeptForTheCriteriaBuilder(): void
    {
        $compressed = self::compress(['limit' => 2, '_criteria' => 'nested']);

        $request = self::createStoreApiRequest(['_criteria' => $compressed]);

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame($compressed, $request->query->get('_criteria'));
    }

    public function testListPayloadDoesNotFail(): void
    {
        $request = self::createStoreApiRequest(['_criteria' => self::compress(['first', 'second'])]);

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame('first', $request->query->get('0'));
        static::assertSame('second', $request->query->get('1'));
    }

    public function testRouteWithCacheSettingsIsHandled(): void
    {
        $request = self::createStoreApiRequest(['_criteria' => self::compress(['limit' => 2])]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, ['sharedMaxAge' => 60]);

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame(2, $request->query->getInt('limit'));
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $scopes
     */
    #[DataProvider('untouchedRequestProvider')]
    public function testRequestIsLeftUntouched(string $method, array $query, array $scopes, bool $cacheable): void
    {
        $request = new Request($query);
        $request->setMethod($method);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $scopes);
        if ($cacheable) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        }

        $this->listener->expandCompressedCriteria(self::createEvent($request));

        static::assertSame($query, $request->query->all());
    }

    /**
     * @return iterable<string, array{method: string, query: array<string, mixed>, scopes: list<string>, cacheable: bool}>
     */
    public static function untouchedRequestProvider(): iterable
    {
        yield 'POST requests carry their parameters in the body' => [
            'method' => Request::METHOD_POST,
            'query' => ['_criteria' => self::compress(['limit' => 2])],
            'scopes' => [StoreApiRouteScope::ID],
            'cacheable' => true,
        ];

        yield 'GET request without compressed criteria' => [
            'method' => Request::METHOD_GET,
            'query' => ['limit' => '2'],
            'scopes' => [StoreApiRouteScope::ID],
            'cacheable' => true,
        ];

        yield 'Store API route that is not marked as cacheable, such as the cart' => [
            'method' => Request::METHOD_GET,
            'query' => ['_criteria' => self::compress(['token' => 'other-cart', 'includes' => ['cart' => ['token']]])],
            'scopes' => [StoreApiRouteScope::ID],
            'cacheable' => false,
        ];

        yield 'only Store API routes are handled' => [
            'method' => Request::METHOD_GET,
            'query' => ['_criteria' => self::compress(['limit' => 2])],
            'scopes' => [ApiRouteScope::ID],
            'cacheable' => true,
        ];

        yield 'route without a scope' => [
            'method' => Request::METHOD_GET,
            'query' => ['_criteria' => self::compress(['limit' => 2])],
            'scopes' => [],
            'cacheable' => true,
        ];
    }

    #[WithoutErrorHandler]
    public function testInvalidCompressedCriteriaIsRejected(): void
    {
        $request = self::createStoreApiRequest(['_criteria' => 'not-base64!!']);

        $this->expectExceptionObject(
            DataAbstractionLayerException::invalidCompressedCriteriaParameter('Failed to decode base64url data')
        );

        $this->listener->expandCompressedCriteria(self::createEvent($request));
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function createStoreApiRequest(array $query): Request
    {
        $request = new Request($query);
        $request->setMethod(Request::METHOD_GET);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        return $request;
    }

    private static function createEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function compress(array $payload): string
    {
        $compressed = gzencode(json_encode($payload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        return Base64::urlEncode($compressed);
    }
}
