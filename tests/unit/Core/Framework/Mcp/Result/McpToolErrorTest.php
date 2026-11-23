<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolError::class)]
class McpToolErrorTest extends TestCase
{
    public function testDefaultsToTheGenericCodeWithoutDetails(): void
    {
        $error = new McpToolError('Something failed');

        static::assertSame('Something failed', $error->message);
        static::assertSame(McpToolError::TOOL_ERROR, $error->code);
        static::assertSame([], $error->details);
    }

    public function testKeepsCodeAndDetails(): void
    {
        $error = new McpToolError('Missing privilege: product:read', McpToolError::MISSING_PRIVILEGE, ['privileges' => ['product:read']]);

        static::assertSame(McpToolError::MISSING_PRIVILEGE, $error->code);
        static::assertSame(['privileges' => ['product:read']], $error->details);
    }
}
