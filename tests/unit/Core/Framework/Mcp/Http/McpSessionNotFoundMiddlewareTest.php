<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Http\McpSessionNotFoundMiddleware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpSessionNotFoundMiddleware::class)]
class McpSessionNotFoundMiddlewareTest extends TestCase
{
    public function testAnswersLikeTheSdkDoesForAnUnknownSession(): void
    {
        $psr17 = new Psr17Factory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new McpSessionNotFoundMiddleware($psr17, $psr17))
            ->process($psr17->createServerRequest('POST', '/api/_mcp'), $handler);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        static::assertSame(
            '{"jsonrpc":"2.0","error":{"code":-32600,"message":"Session not found or has expired."}}',
            (string) $response->getBody(),
        );
    }
}
