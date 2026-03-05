<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
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
    /** @phpstan-ignore property.unresolvableNativeType */
    private Server&MockObject $server;

    private HttpMessageFactoryInterface&MockObject $httpMessageFactory;

    private HttpFoundationFactoryInterface&MockObject $httpFoundationFactory;

    private ResponseFactoryInterface&MockObject $responseFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private RateLimiter&MockObject $rateLimiter;

    private McpServerController $controller;

    protected function setUp(): void
    {
        /** @phpstan-ignore method.unresolvableReturnType */
        $this->server = $this->createMock(Server::class);
        $this->httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        $this->httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);

        $this->controller = new McpServerController(
            $this->server,
            $this->httpMessageFactory,
            $this->httpFoundationFactory,
            $this->responseFactory,
            $this->streamFactory,
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

    public function testSuccessfulHandleReturnsResponse(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $psrRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $symfonyResponse = new Response('ok', 200);

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP, '192.168.1.1');

        $this->httpMessageFactory->expects($this->once())
            ->method('createRequest')
            ->with($request)
            ->willReturn($psrRequest);

        $this->server->expects($this->once())
            ->method('run')
            ->with(static::anything())
            ->willReturn($psrResponse);

        $this->httpFoundationFactory->expects($this->once())
            ->method('createResponse')
            ->with($psrResponse, false)
            ->willReturn($symfonyResponse);

        $result = $this->controller->handle($request);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('ok', $result->getContent());
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
}
