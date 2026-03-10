<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
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
}
