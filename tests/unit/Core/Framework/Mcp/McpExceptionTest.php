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
    public function testThrottledPreservesPreviousException(): void
    {
        $previous = new \RuntimeException('rate limit hit');
        $e = McpException::throttled(30, $previous);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $e->getStatusCode());
        static::assertSame('MCP__THROTTLED', $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testOAuthProtectedResourceMetadataUnavailableIsNotFoundWithTheReason(): void
    {
        $previous = new \InvalidArgumentException('must use https');
        $e = McpException::oauthProtectedResourceMetadataUnavailable('http://shop.example/api/_mcp', $previous);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame('MCP__OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE', $e->getErrorCode());
        static::assertSame('No OAuth protected resource metadata for "http://shop.example/api/_mcp": must use https', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testToolResultNotFoundNamesTheIdWithoutSayingWhoStoredIt(): void
    {
        $e = McpException::toolResultNotFound('abc');

        static::assertSame('Tool result "abc" not found. It may have expired or been stored by another caller.', $e->uri);
    }
}
