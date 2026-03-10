<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(McpServerController::class)]
#[Package('framework')]
class McpServerControllerTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private McpServerController $controller;

    protected function setUp(): void
    {
        $server = Server::builder()->build();
        $this->rateLimiter = $this->createMock(RateLimiter::class);

        $this->controller = new McpServerController(
            $server,
            static::createStub(HttpMessageFactoryInterface::class),
            static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(ResponseFactoryInterface::class),
            static::createStub(StreamFactoryInterface::class),
            $this->rateLimiter,
        );
    }

    public function testRateLimitExceededThrowsMcpException(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP, '192.168.1.1')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $this->expectException(McpException::class);

        $this->controller->handle($request);
    }

    public function testRateLimitUsesOAuthTokenIdWhenPresent(): void
    {
        $request = new Request();
        $request->attributes->set('oauth_access_token_id', 'token-123');

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP, 'token-123')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $this->expectException(McpException::class);

        $this->controller->handle($request);
    }

    public function testRateLimitUsesUnknownWhenNoTokenOrIp(): void
    {
        $request = new Request();

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP, 'unknown')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $this->expectException(McpException::class);

        $this->controller->handle($request);
    }

    public function testHandleReturnsResponseForValidMcpRequest(): void
    {
        $psr17 = new Psr17Factory();

        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $psrRequest = new ServerRequest('POST', '/api/_mcp', ['Content-Type' => 'application/json'], $body);

        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $symfonyResponse = new Response('', 200);
        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn($symfonyResponse);

        $rateLimiter = static::createStub(RateLimiter::class);

        $controller = new McpServerController(
            Server::builder()->build(),
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17,
            $psr17,
            $rateLimiter,
        );

        $response = $controller->handle(new Request());

        static::assertSame(200, $response->getStatusCode());
    }

    public function testHandleDetectsStreamedResponse(): void
    {
        $psr17 = new Psr17Factory();

        $psrRequest = new ServerRequest('GET', '/api/_mcp');

        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->expects($this->once())
            ->method('createResponse')
            ->with(
                static::anything(),
                false,
            )
            ->willReturn(new Response('', 405));

        $rateLimiter = static::createStub(RateLimiter::class);

        $controller = new McpServerController(
            Server::builder()->build(),
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17,
            $psr17,
            $rateLimiter,
        );

        $response = $controller->handle(new Request());

        static::assertSame(405, $response->getStatusCode());
    }
}
