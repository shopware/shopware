<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Schema\Enum\ProtocolVersion;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * What a negotiated MCP protocol revision supports in a tool result, and therefore which format
 * {@see McpToolResultRenderer} sends. This is the one place that maps revisions to capabilities: a new
 * revision that changes the result format is one more entry in {@see self::forRevision()}.
 */
#[Package('framework')]
final readonly class McpToolResultCapabilities
{
    /**
     * @param bool $specFormat the result is sent in the MCP format: `structuredContent`, `resource_link` and
     *                         the plain data in the text block. Without it, the text block is the deprecated
     *                         legacy `{"success": …}` envelope, the only format these clients know
     * @param bool $objectOnlyStructuredContent `structuredContent` has to be a JSON object; a list or a scalar is wrapped
     * @param int $inlineLimit the largest result sent inline, counted as the text blocks plus the encoded `structuredContent`
     */
    private function __construct(
        public bool $specFormat,
        public bool $objectOnlyStructuredContent,
        public int $inlineLimit,
        public McpToolResultStoredFormat $storedFormat,
    ) {
    }

    public static function forRevision(ProtocolVersion $protocolVersion): self
    {
        // Newest first: the first entry the revision reaches applies.
        return match (true) {
            // Since 2026-07-28 `structuredContent` may be any JSON value.
            $protocolVersion->isAtLeast(ProtocolVersion::V2026_07_28) => new self(
                specFormat: true,
                objectOnlyStructuredContent: false,
                inlineLimit: McpToolResultRenderer::MAX_INLINE_SIZE,
                storedFormat: McpToolResultStoredFormat::Spec,
            ),
            // 2025-06-18 introduced `structuredContent` and `resource_link`.
            $protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18) => new self(
                specFormat: true,
                objectOnlyStructuredContent: true,
                inlineLimit: McpToolResultRenderer::MAX_INLINE_SIZE,
                storedFormat: McpToolResultStoredFormat::Spec,
            ),
            default => new self(
                specFormat: false,
                objectOnlyStructuredContent: true,
                inlineLimit: McpToolResultRenderer::MAX_INLINE_SIZE_TEXT_ONLY,
                storedFormat: McpToolResultStoredFormat::Legacy,
            ),
        };
    }
}
