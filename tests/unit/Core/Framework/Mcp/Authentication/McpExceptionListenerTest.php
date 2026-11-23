<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use League\OAuth2\Server\Exception\OAuthServerException;
use Mcp\Schema\JsonRpc\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Shopware\Core\Framework\Mcp\Authentication\McpOAuthMetadata;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
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
        $listener = $this->createListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'POST');

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('invalid_client', $body['error']);
        static::assertStringContainsString('/api/_mcp', $body['error_description']);
        static::assertStringContainsString('/store-api/_mcp', $body['error_description']);
    }

    #[TestDox('returns OAuth error for POST /register regardless of accept header')]
    public function testHandlesPostRegisterWithoutJsonAccept(): void
    {
        $listener = $this->createListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'POST');

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[TestDox('ignores GET /register so storefront customer-register navigation is unaffected')]
    public function testIgnoresGetRegister(): void
    {
        $listener = $this->createListener();
        $event = $this->createExceptionEvent('/register', '', new \RuntimeException('some error'), method: 'GET');

        $listener->onException($event);

        static::assertNull($event->getResponse());
    }

    #[TestDox('ignores exceptions on non-MCP routes')]
    public function testIgnoresNonMcpRoute(): void
    {
        $listener = $this->createListener();
        $event = $this->createExceptionEvent('/some/path', 'api.some.other.route', new \RuntimeException('error'));

        $listener->onException($event);

        static::assertNull($event->getResponse());
    }

    #[TestDox('converts HTTP exception on MCP route to JSON-RPC error')]
    public function testConvertsHttpExceptionToJsonRpcError(): void
    {
        $listener = $this->createListener();
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

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(McpExceptionListener::CODE_UNAUTHORIZED, $body['error']['code']);
    }

    #[TestDox('converts HTTP exception on Store API MCP route to JSON-RPC error')]
    public function testConvertsStoreApiHttpExceptionToJsonRpcError(): void
    {
        $listener = $this->createListener();
        $httpException = new class extends \RuntimeException {
            public function getStatusCode(): int
            {
                return Response::HTTP_UNAUTHORIZED;
            }
        };

        $event = $this->createExceptionEvent('/store-api/_mcp', 'store-api.mcp.endpoint', $httpException);

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(McpExceptionListener::CODE_UNAUTHORIZED, $body['error']['code']);
        static::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    #[TestDox('points a 401 without credentials to the protected resource metadata')]
    public function testUnauthorizedWithoutCredentialsCarriesResourceMetadata(): void
    {
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', OAuthServerException::accessDenied('Missing "Authorization" header'));

        $this->createListener()->onException($event);

        static::assertSame(
            'Bearer resource_metadata="http://shop.example/.well-known/oauth-protected-resource/api/_mcp"',
            $event->getResponse()?->headers->get('WWW-Authenticate')
        );
    }

    #[TestDox('keeps the query of the MCP request in the resource metadata URL and escapes it for the quoted string')]
    public function testUnauthorizedChallengeKeepsTheQueryOfTheRequest(): void
    {
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', OAuthServerException::accessDenied('Missing "Authorization" header'));
        $event->getRequest()->server->set('QUERY_STRING', 'toolsets=orders&x="y\\');

        $this->createListener()->onException($event);

        static::assertSame(
            'Bearer resource_metadata="http://shop.example/.well-known/oauth-protected-resource/api/_mcp?toolsets=orders&x=\\"y\\\\"',
            $event->getResponse()?->headers->get('WWW-Authenticate')
        );
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('credentialHeaderProvider')]
    #[TestDox('reports invalid credentials as invalid_token in the 401 challenge')]
    public function testUnauthorizedWithCredentialsCarriesInvalidToken(array $headers): void
    {
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', OAuthServerException::accessDenied('Access token could not be verified'), $headers);

        $this->createListener()->onException($event);

        static::assertSame(
            'Bearer error="invalid_token", resource_metadata="http://shop.example/.well-known/oauth-protected-resource/api/_mcp"',
            $event->getResponse()?->headers->get('WWW-Authenticate')
        );
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function credentialHeaderProvider(): iterable
    {
        yield 'bearer token' => [['Authorization' => 'Bearer expired']];
        yield 'access key headers' => [['sw-access-key' => 'SWIA', 'sw-secret-access-key' => 'secret']];
    }

    #[TestDox('sends no challenge with a 403 on the MCP route')]
    public function testForbiddenCarriesNoChallenge(): void
    {
        $httpException = new class extends \RuntimeException {
            public function getStatusCode(): int
            {
                return Response::HTTP_FORBIDDEN;
            }
        };
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', $httpException);

        $this->createListener()->onException($event);

        static::assertFalse($event->getResponse()?->headers->has('WWW-Authenticate'));
    }

    #[TestDox('converts OAuth exception on MCP route to 401 JSON-RPC error with the hint in the message')]
    public function testConvertsOAuthServerExceptionToUnauthorizedJsonRpcError(): void
    {
        $listener = $this->createListener();
        $oauthException = OAuthServerException::accessDenied('Missing "Authorization" header');

        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', $oauthException);

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(McpExceptionListener::CODE_UNAUTHORIZED, $body['error']['code']);
        static::assertStringContainsString('Missing "Authorization" header', $body['error']['message']);
    }

    #[TestDox('converts OAuth exception without a hint to 401 JSON-RPC error with the plain message')]
    public function testConvertsOAuthServerExceptionWithoutHint(): void
    {
        $listener = $this->createListener();
        $oauthException = OAuthServerException::accessDenied();

        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', $oauthException);

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(McpExceptionListener::CODE_UNAUTHORIZED, $body['error']['code']);
        static::assertSame($oauthException->getMessage(), $body['error']['message']);
    }

    #[TestDox('converts generic exception on MCP route to 500 JSON-RPC error')]
    public function testConvertsGenericExceptionToServerError(): void
    {
        $listener = $this->createListener();
        $event = $this->createExceptionEvent('/api/_mcp', 'api.mcp.endpoint', new \RuntimeException('Something went wrong'));

        $listener->onException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
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
        $listener = $this->createListener();

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

        $content = $event->getResponse()?->getContent();
        static::assertNotFalse($content);
        static::assertNotNull($content);
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($expectedCode, $body['error']['code']);
    }

    private function createListener(): McpExceptionListener
    {
        $allowedHosts = static::createStub(McpAllowedHostsProvider::class);
        $allowedHosts->method('getAllowedHosts')->willReturn([]);

        return new McpExceptionListener(new McpOAuthMetadata($allowedHosts, 'http://shop.example'));
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
