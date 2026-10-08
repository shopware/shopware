<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * A pointer to data the client fetches separately instead of receiving it inline, for example a
 * large result stored in `mcp_tool_result_cache`. MCP renders it as a `resource_link` content block.
 */
#[Package('framework')]
final readonly class McpToolResultLink
{
    /**
     * @param int|null $size the size of the linked data in bytes, when known
     */
    public function __construct(
        public string $uri,
        public string $name,
        public ?string $description = null,
        public ?string $mimeType = null,
        public ?int $size = null,
    ) {
    }
}
