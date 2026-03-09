<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpException::class)]
class McpExceptionTest extends TestCase
{
    public function testUnsupportedKeyType(): void
    {
        $e = McpException::unsupportedKeyType();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $e->getStatusCode());
        static::assertSame('MCP__UNSUPPORTED_KEY_TYPE', $e->getErrorCode());
        static::assertStringContainsString('integration access keys', $e->getMessage());
    }

    public function testInvalidCredentials(): void
    {
        $e = McpException::invalidCredentials();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $e->getStatusCode());
        static::assertSame('MCP__INVALID_CREDENTIALS', $e->getErrorCode());
        static::assertStringContainsString('Invalid integration credentials', $e->getMessage());
    }

    public function testThrottled(): void
    {
        $previous = new \RuntimeException('rate limit hit');
        $e = McpException::throttled(30, $previous);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $e->getStatusCode());
        static::assertSame('MCP__THROTTLED', $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }
}
