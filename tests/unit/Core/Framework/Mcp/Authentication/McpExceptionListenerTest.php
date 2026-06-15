<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use Mcp\Schema\JsonRpc\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpExceptionListener::class)]
class McpExceptionListenerTest extends TestCase
{
    public function testSubscribedEventsContainsExceptionEvent(): void
    {
        $events = McpExceptionListener::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        static::assertSame(['onException', 10], $events[KernelEvents::EXCEPTION]);
    }

    #[TestDox('returns OAuth error for POST /register fallback path')]
    public function testHandlesPostRegister(): void
    {
        $listener = new McpExceptionListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'POST');

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        static::assertSame('invalid_client', $body['error']);
        static::assertStringContainsString('/api/_mcp', $body['error_description']);
    }

    #[TestDox('returns OAuth error for POST /register regardless of accept header')]
    public function testHandlesPostRegisterWithoutJsonAccept(): void
    {
        $listener = new McpExceptionListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'POST');

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[TestDox('ignores GET /register so storefront customer-register navigation is unaffected')]
    public function testIgnoresGetRegister(): void
    {
        $listener = new McpExceptionListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'GET');

        $listener->onException($event);

        static::assertNull($event->getResponse());
    }

    #[TestDox('ignores exceptions on non-MCP routes')]
    public function testIgnoresNonMcpRoute(): void
    {
        $listener = new McpExceptionListener();
        $event = $this->createExceptionEvent('/some/path', 'api.some.other.route', new \RuntimeException('error'));

        $listener->onException($event);

        static::assertNull($event->getResponse());
    }

    #[TestDox('converts HTTP exception on MCP route to JSON-RPC error')]
    public function testConvertsHttpExceptionToJsonRpcError(): void
    {
        $listener = new McpExceptionListener();
        $httpException = new class extends \RuntimeException {
            public function getStatusCode(): int
            {
                return Response::HTTP_UNAUTHORIZED;
            }
        };

        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', $httpException);

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        static::assertSame(-32001, $body['error']['code']);
    }

    #[TestDox('converts generic exception on MCP route to 500 JSON-RPC error')]
    public function testConvertsGenericExceptionToServerError(): void
    {
        $listener = new McpExceptionListener();
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', new \RuntimeException('Something went wrong'));

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        static::assertSame(Error::SERVER_ERROR, $body['error']['code']);
        static::assertSame('Something went wrong', $body['error']['message']);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function jsonRpcCodeProvider(): iterable
    {
        yield '401 maps to -32001' => [401, -32001];
        yield '403 maps to -32001' => [403, -32001];
        yield '429 maps to -32029' => [429, -32029];
        yield '400 maps to INVALID_REQUEST' => [400, Error::INVALID_REQUEST];
        yield '404 maps to INVALID_REQUEST' => [404, Error::INVALID_REQUEST];
        yield '500 maps to SERVER_ERROR' => [500, Error::SERVER_ERROR];
        yield '503 maps to SERVER_ERROR' => [503, Error::SERVER_ERROR];
    }

    #[DataProvider('jsonRpcCodeProvider')]
    #[TestDox('maps HTTP $httpCode to JSON-RPC code $expectedCode')]
    public function testJsonRpcCodeMapping(int $httpCode, int $expectedCode): void
    {
        $listener = new McpExceptionListener();

        $httpException = new class($httpCode) extends \RuntimeException {
            public function __construct(private readonly int $statusCode)
            {
                parent::__construct('error message');
            }

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }
        };

        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', $httpException);

        $listener->onException($event);

        $body = json_decode((string) $event->getResponse()?->getContent(), true);
        static::assertSame($expectedCode, $body['error']['code']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function createExceptionEvent(string $pathInfo, string $routeName, \Throwable $throwable, array $headers = [], string $method = 'GET'): ExceptionEvent
    {
        $request = Request::create($pathInfo, $method);

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        $request->attributes->set('_route', $routeName);

        return new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
