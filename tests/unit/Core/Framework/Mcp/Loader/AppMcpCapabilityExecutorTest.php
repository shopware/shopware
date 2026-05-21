<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AppMcpCapabilityExecutor::class)]
#[Package('framework')]
class AppMcpCapabilityExecutorTest extends TestCase
{
    private MockHandler $mockHandler;

    private AppMcpCapabilityExecutor $executor;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->executor = new AppMcpCapabilityExecutor(
            $client,
            'https://shop.example.com',
            $this->createShopIdProvider(),
            30,
        );
    }

    public function testSuccessfulExecutionReturnsResponseBody(): void
    {
        $expectedBody = '{"result":"ok"}';
        $this->mockHandler->append(new Response(200, [], $expectedBody));

        $result = $this->executor->execute(
            'sync-orders',
            'test-secret',
            'https://app.example.com/mcp/sync',
            ['foo' => 'bar'],
            '1.0.0',
        );

        static::assertSame($expectedBody, $result);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertSame('POST', $lastRequest->getMethod());
        static::assertSame('https://app.example.com/mcp/sync', (string) $lastRequest->getUri());
        static::assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $lastRequest->getHeaderLine('Accept'));
        static::assertNotEmpty($lastRequest->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE));

        $body = json_decode($lastRequest->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('sync-orders', $body['tool']);
        static::assertSame(['foo' => 'bar'], $body['arguments']);
        static::assertSame('https://shop.example.com', $body['source']['url']);
        static::assertSame('test-shop-id', $body['source']['shopId']);
        static::assertSame('1.0.0', $body['source']['appVersion']);
    }

    public function testFailedExecutionReturnsJsonError(): void
    {
        $this->mockHandler->append(new \RuntimeException('Connection refused'));

        $result = $this->executor->execute(
            'sync-orders',
            'test-secret',
            'https://app.example.com/mcp/sync',
            [],
        );

        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertFalse($decoded['success']);
        static::assertStringContainsString('sync-orders', $decoded['error']);
        static::assertStringContainsString('Connection refused', $decoded['error']);
    }

    public function testResponseWithoutSuccessKeyLogsWarning(): void
    {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"result":"ok"}'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');
        $logger->expects($this->once())->method('warning')
            ->with(static::stringContains('missing "success" key'));

        $executor = new AppMcpCapabilityExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            $this->createShopIdProvider(),
            30,
            $logger,
        );

        $executor->execute('my-tool', 'secret', 'https://example.com', []);
    }

    public function testExceptionWithLoggerLogsError(): void
    {
        $mock = new MockHandler();
        $mock->append(new \RuntimeException('timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with(static::stringContains('execution failed'));

        $executor = new AppMcpCapabilityExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            $this->createShopIdProvider(),
            30,
            $logger,
        );

        $result = $executor->execute('my-tool', 'secret', 'https://example.com', []);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
    }

    public function testSuccessResponseWithSuccessKeyDoesNotLogWarning(): void
    {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"success":true,"data":{}}'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');
        $logger->expects($this->never())->method('warning');

        $executor = new AppMcpCapabilityExecutor(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://shop.example.com',
            $this->createShopIdProvider(),
            30,
            $logger,
        );

        $executor->execute('my-tool', 'secret', 'https://example.com', []);
    }

    public function testHmacSignatureIsSentInHeader(): void
    {
        $this->mockHandler->append(new Response(200, [], '{}'));

        $this->executor->execute('my-tool', 'secret', 'https://example.com', []);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);

        $body = $lastRequest->getBody()->getContents();
        $expectedSignature = hash_hmac('sha256', $body, 'secret');

        $signature = $lastRequest->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE);
        static::assertSame($expectedSignature, $signature);
    }

    public function testNullAppSecretSkipsHmacHeader(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"success":true}'));

        $this->executor->execute('my-tool', null, 'https://example.com', []);

        $lastRequest = $this->mockHandler->getLastRequest();
        static::assertNotNull($lastRequest);
        static::assertEmpty($lastRequest->getHeaderLine(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }

    public function testInternalUrlWithMissingServicesReturnsError(): void
    {
        $result = $this->executor->execute('my-tool', null, '/api/script/my-tool', []);

        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('requires kernel', $data['error']);
    }

    public function testInternalUrlWithNoActiveRequestReturnsError(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);

        $executor = $this->makeExecutorWithSubrequest(
            kernel: static::createStub(KernelInterface::class),
            requestStack: $requestStack,
            router: static::createStub(RouterInterface::class),
        );

        $result = $executor->execute('my-tool', null, '/api/script/my-tool', []);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('No active request context', $data['error']);
    }

    public function testInternalUrlDispatchesSubrequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->with('/api/script/my-tool')->willReturn(['_route' => 'api.script.run']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('handle')->with(
            static::callback(static function (Request $r): bool {
                $body = json_decode($r->getContent(), true);

                return $r->getMethod() === 'POST'
                    && $r->headers->get('Content-Type') === 'application/json'
                    && $body === ['arguments' => ['name' => 'World']];
            }),
            HttpKernelInterface::SUB_REQUEST,
        )->willReturn(new SymfonyResponse('{"success":true,"data":{"message":"Hello"}}'));

        $executor = $this->makeExecutorWithSubrequest($kernel, $requestStack, $router);

        $result = $executor->execute('MyApp-my-tool', null, '/api/script/my-tool', ['name' => 'World']);
        static::assertSame('{"success":true,"data":{"message":"Hello"}}', $result);
    }

    public function testSubrequestPropagatesAuthorizationHeaderForOAuthValidator(): void
    {
        $parent = new Request();
        $parent->headers->set('Authorization', 'Bearer my-token-123');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($parent);

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => 'api.script.run']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('handle')->with(
            // Bearer validator reads Authorization from server params (PSR-7 conversion),
            // not the HeaderBag — verify both are populated.
            static::callback(static fn (Request $r): bool => $r->server->get('HTTP_AUTHORIZATION') === 'Bearer my-token-123'
                && $r->headers->get('Authorization') === 'Bearer my-token-123'),
            HttpKernelInterface::SUB_REQUEST,
        )->willReturn(new SymfonyResponse('{"success":true}'));

        $executor = $this->makeExecutorWithSubrequest($kernel, $requestStack, $router);
        $executor->execute('my-tool', null, '/api/script/my-tool', []);
    }

    public function testSubrequestPropagatesPreAuthenticatedAttributesForAccessKeyAuth(): void
    {
        $parent = new Request();
        $parent->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'mcp-SWIAKEY123');
        $parent->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAKEY123');
        $parent->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($parent);

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => 'api.script.run']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('handle')->with(
            static::callback(static fn (Request $r): bool => $r->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED) === true
                && $r->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID) === 'mcp-SWIAKEY123'
                && $r->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID) === 'SWIAKEY123'),
            HttpKernelInterface::SUB_REQUEST,
        )->willReturn(new SymfonyResponse('{"success":true}'));

        $executor = $this->makeExecutorWithSubrequest($kernel, $requestStack, $router);
        $executor->execute('my-tool', null, '/api/script/my-tool', []);
    }

    public function testSubrequestSendsJsonBodyNotFormUrlencoded(): void
    {
        // Earlier impl POSTed form params and then copied parent headers, which
        // stomped Content-Type to application/json with a form-urlencoded body.
        // The request bag came out empty. JSON body + explicit JSON content-type
        // keeps the two in sync.
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => 'api.script.run']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('handle')->with(
            static::callback(static function (Request $r): bool {
                $body = json_decode($r->getContent(), true);

                return $r->headers->get('Content-Type') === 'application/json'
                    && \is_array($body)
                    && $body === ['arguments' => ['entity' => 'product', 'limit' => 5]];
            }),
            HttpKernelInterface::SUB_REQUEST,
        )->willReturn(new SymfonyResponse('{"success":true}'));

        $executor = $this->makeExecutorWithSubrequest($kernel, $requestStack, $router);
        $executor->execute('my-tool', null, '/api/script/my-tool', ['entity' => 'product', 'limit' => 5]);
    }

    public function testSubrequestExceptionReturnsError(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willThrowException(new \RuntimeException('Route not found'));

        $executor = $this->makeExecutorWithSubrequest(
            kernel: static::createStub(KernelInterface::class),
            requestStack: $requestStack,
            router: $router,
        );

        $result = $executor->execute('my-tool', null, '/api/script/missing', []);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('internal execution failed', $data['error']);
        static::assertStringContainsString('Route not found', $data['error']);
    }

    public function testSubrequestEmptyResponseReturnsErrorJson(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());

        $router = $this->createMock(RouterInterface::class);
        $router->method('match')->willReturn(['_route' => 'api.script.run']);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('handle')->willReturn(new SymfonyResponse(''));

        $executor = $this->makeExecutorWithSubrequest($kernel, $requestStack, $router);

        $result = $executor->execute('my-tool', null, '/api/script/empty', []);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Empty response', $data['error']);
    }

    private function makeExecutorWithSubrequest(
        KernelInterface $kernel,
        RequestStack $requestStack,
        RouterInterface $router,
    ): AppMcpCapabilityExecutor {
        return new AppMcpCapabilityExecutor(
            new Client(['handler' => HandlerStack::create($this->mockHandler)]),
            'https://shop.example.com',
            $this->createShopIdProvider(),
            30,
            null,
            $kernel,
            $requestStack,
            $router,
        );
    }

    private function createShopIdProvider(): ShopIdProvider
    {
        $provider = $this->createMock(ShopIdProvider::class);
        $provider->method('getShopId')->willReturn(ShopId::v2('test-shop-id'));

        return $provider;
    }
}
