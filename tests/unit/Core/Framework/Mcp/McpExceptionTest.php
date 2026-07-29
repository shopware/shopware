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
}
