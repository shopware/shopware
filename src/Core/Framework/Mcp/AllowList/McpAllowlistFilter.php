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
     * Removes tools not present in $allowlist from a decoded tools/list JSON-RPC response.
     *
     * @param array<string, mixed> $responseData decoded JSON-RPC response body
     * @param list<string> $allowlist
     *
     * @return array<string, mixed> filtered response data
     */
    public function filterToolsListResponse(array $responseData, array $allowlist): array
    {
        if (!isset($responseData['result'][McpAllowlistProvider::TOOLS]) || !\is_array($responseData['result'][McpAllowlistProvider::TOOLS])) {
            return $responseData;
        }

        $responseData['result'][McpAllowlistProvider::TOOLS] = array_values(
            array_filter(
                $responseData['result'][McpAllowlistProvider::TOOLS],
                static fn (mixed $tool): bool => \is_array($tool) && \in_array($tool['name'] ?? '', $allowlist, true),
            ),
        );

        return $responseData;
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
     * Removes resources not present in $allowlist from a decoded resources/list JSON-RPC response.
     *
     * @param array<string, mixed> $responseData decoded JSON-RPC response body
     * @param list<string> $allowlist
     *
     * @return array<string, mixed> filtered response data
     */
    public function filterResourcesListResponse(array $responseData, array $allowlist): array
    {
        if (!isset($responseData['result'][McpAllowlistProvider::RESOURCES]) || !\is_array($responseData['result'][McpAllowlistProvider::RESOURCES])) {
            return $responseData;
        }

        $responseData['result'][McpAllowlistProvider::RESOURCES] = array_values(
            array_filter(
                $responseData['result'][McpAllowlistProvider::RESOURCES],
                static fn (mixed $resource): bool => \is_array($resource) && \in_array($resource['uri'] ?? '', $allowlist, true),
            ),
        );

        return $responseData;
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

    /**
     * Removes prompts not present in $allowlist from a decoded prompts/list JSON-RPC response.
     *
     * @param array<string, mixed> $responseData decoded JSON-RPC response body
     * @param list<string> $allowlist
     *
     * @return array<string, mixed> filtered response data
     */
    public function filterPromptsListResponse(array $responseData, array $allowlist): array
    {
        if (!isset($responseData['result'][McpAllowlistProvider::PROMPTS]) || !\is_array($responseData['result'][McpAllowlistProvider::PROMPTS])) {
            return $responseData;
        }

        $responseData['result'][McpAllowlistProvider::PROMPTS] = array_values(
            array_filter(
                $responseData['result'][McpAllowlistProvider::PROMPTS],
                static fn (mixed $prompt): bool => \is_array($prompt) && \in_array($prompt['name'] ?? '', $allowlist, true),
            ),
        );

        return $responseData;
    }
}
