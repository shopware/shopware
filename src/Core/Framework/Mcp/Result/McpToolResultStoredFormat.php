<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * The format of a tool result stored in `mcp_tool_result_cache`, kept in its `mime_type` column. The
 * value is the stored media type, which `resources/read` doesn't send: every format is served as
 * {@see self::mimeType()}.
 */
#[Package('framework')]
enum McpToolResultStoredFormat: string
{
    /**
     * The `{"success": …}` envelope, for clients before 2025-06-18. It is the column default, so every row
     * written before the format was recorded reads as this one.
     */
    case Legacy = 'application/json';

    /**
     * The plain result data, for clients since 2025-06-18, which get the metadata with the link instead.
     */
    case Spec = 'application/vnd.shopware.mcp-tool-result+json';

    /**
     * The media type `resources/read` sends for a row whose `mime_type` column holds $storedMimeType. A
     * value that is no format, stored by another caller of the storage, is sent as it is.
     */
    public static function servedMimeType(string $storedMimeType): string
    {
        return self::tryFrom($storedMimeType)?->mimeType() ?? $storedMimeType;
    }

    public function mimeType(): string
    {
        return 'application/json';
    }
}
