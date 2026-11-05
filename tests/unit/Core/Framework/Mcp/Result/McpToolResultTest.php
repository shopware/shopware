<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResult::class)]
class McpToolResultTest extends TestCase
{
    public function testSuccessCarriesDataMetaAndSummary(): void
    {
        $success = McpToolResult::success(['a' => 1], ['dryRun' => true], 'One item');

        static::assertSame(['a' => 1], $success->data);
        static::assertSame('One item', $success->summary);
        static::assertSame(['dryRun' => true], $success->meta);
        static::assertNull($success->error);
        static::assertFalse($success->isError());
    }

    public function testFailureCarriesAnErrorWithItsCodeAndDetails(): void
    {
        $failure = McpToolResult::failure('Nope', McpToolError::NOT_FOUND, ['id' => 'x']);

        static::assertTrue($failure->isError());
        static::assertEquals(new McpToolError('Nope', McpToolError::NOT_FOUND, ['id' => 'x']), $failure->error);
        static::assertNull($failure->data);
    }

    public function testFailureDefaultsToTheGenericErrorCode(): void
    {
        static::assertSame(McpToolError::TOOL_ERROR, McpToolResult::failure('Nope')->error?->code);
    }

    public function testTimestampsAreOptional(): void
    {
        $generatedAt = new \DateTimeImmutable('2026-09-28T10:00:00+00:00');
        $expiresAt = new \DateTimeImmutable('2026-09-28T11:00:00+00:00');

        $result = new McpToolResult(data: [], generatedAt: $generatedAt, expiresAt: $expiresAt);

        static::assertSame($generatedAt, $result->generatedAt);
        static::assertSame($expiresAt, $result->expiresAt);
        static::assertNull(McpToolResult::success([])->generatedAt);
    }
}
