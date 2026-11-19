<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Schema\Enum\ProtocolVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultCapabilities;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultStoredFormat;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultCapabilities::class)]
class McpToolResultCapabilitiesTest extends TestCase
{
    /**
     * @return iterable<string, array{ProtocolVersion, bool, bool, int, McpToolResultStoredFormat}>
     */
    public static function revisionProvider(): iterable
    {
        yield '2024-11-05 gets the legacy envelope' => [ProtocolVersion::V2024_11_05, false, true, McpToolResultRenderer::MAX_INLINE_SIZE_TEXT_ONLY, McpToolResultStoredFormat::Legacy];
        yield '2025-03-26 gets the legacy envelope' => [ProtocolVersion::V2025_03_26, false, true, McpToolResultRenderer::MAX_INLINE_SIZE_TEXT_ONLY, McpToolResultStoredFormat::Legacy];
        yield '2025-06-18 introduced structuredContent and resource_link' => [ProtocolVersion::V2025_06_18, true, true, McpToolResultRenderer::MAX_INLINE_SIZE, McpToolResultStoredFormat::Spec];
        yield '2025-11-25 gets the MCP format' => [ProtocolVersion::V2025_11_25, true, true, McpToolResultRenderer::MAX_INLINE_SIZE, McpToolResultStoredFormat::Spec];
        yield '2026-07-28 allows any JSON value as structuredContent' => [ProtocolVersion::V2026_07_28, true, false, McpToolResultRenderer::MAX_INLINE_SIZE, McpToolResultStoredFormat::Spec];
    }

    #[DataProvider('revisionProvider')]
    public function testMapsEveryRevisionToItsResultFormat(
        ProtocolVersion $protocolVersion,
        bool $specFormat,
        bool $objectOnlyStructuredContent,
        int $inlineLimit,
        McpToolResultStoredFormat $storedFormat,
    ): void {
        $capabilities = McpToolResultCapabilities::forRevision($protocolVersion);

        static::assertSame($specFormat, $capabilities->specFormat);
        static::assertSame($objectOnlyStructuredContent, $capabilities->objectOnlyStructuredContent);
        static::assertSame($inlineLimit, $capabilities->inlineLimit);
        static::assertSame($storedFormat, $capabilities->storedFormat);
    }

    public function testCoversEveryRevisionOfTheSdk(): void
    {
        // A revision the SDK adds falls under the newest entry it reaches; this fails so it gets a look.
        static::assertCount(5, ProtocolVersion::cases(), 'check McpToolResultCapabilities::forRevision() for the new revision, then add it to the provider');
    }
}
