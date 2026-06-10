<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Pure allowlist-filtering logic for MCP tool, resource, and prompt calls and list responses.
 * Contains no HTTP or JSON concerns — operates on decoded data structures only.
 */
#[Package('framework')]
class McpAllowlistFilter
{
    /**
     * Returns true when a tools/call for $toolName should be rejected.
     *
     * @param list<string> $allowlist
     */
    public function isToolCallDenied(string $toolName, array $allowlist): bool
    {
        return !\in_array($toolName, $allowlist, true);
    }

    /**
     * Returns true when a resources/read for $resourceUri should be rejected.
     *
     * shopware://tool-result/ URIs are always allowed — they are session-scoped
     * internal resources produced by tool calls, not app-registered resources.
     *
     * @param list<string> $allowlist
     */
    public function isResourceReadDenied(string $resourceUri, array $allowlist): bool
    {
        if (str_starts_with($resourceUri, 'shopware://tool-result/')) {
            return false;
        }

        return !\in_array($resourceUri, $allowlist, true);
    }

    /**
     * Returns true when a prompts/get for $promptName should be rejected.
     *
     * @param list<string> $allowlist
     */
    public function isPromptGetDenied(string $promptName, array $allowlist): bool
    {
        return !\in_array($promptName, $allowlist, true);
    }
}
