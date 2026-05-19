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
     * @param list<string> $allowlist
     */
    public function filterToolsListResponse(\stdClass $responseData, array $allowlist): \stdClass
    {
        $result = $responseData->result ?? null;
        if (!$result instanceof \stdClass) {
            return $responseData;
        }

        $tools = $result->{McpAllowlistProvider::TOOLS} ?? null;
        if (!\is_array($tools)) {
            return $responseData;
        }

        $result->{McpAllowlistProvider::TOOLS} = array_values(
            array_filter(
                $tools,
                static fn (mixed $tool): bool => $tool instanceof \stdClass && \in_array($tool->name ?? '', $allowlist, true),
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
     * @param list<string> $allowlist
     */
    public function filterResourcesListResponse(\stdClass $responseData, array $allowlist): \stdClass
    {
        $result = $responseData->result ?? null;
        if (!$result instanceof \stdClass) {
            return $responseData;
        }

        $resources = $result->{McpAllowlistProvider::RESOURCES} ?? null;
        if (!\is_array($resources)) {
            return $responseData;
        }

        $result->{McpAllowlistProvider::RESOURCES} = array_values(
            array_filter(
                $resources,
                static fn (mixed $resource): bool => $resource instanceof \stdClass && \in_array($resource->uri ?? '', $allowlist, true),
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
     * @param list<string> $allowlist
     */
    public function filterPromptsListResponse(\stdClass $responseData, array $allowlist): \stdClass
    {
        $result = $responseData->result ?? null;
        if (!$result instanceof \stdClass) {
            return $responseData;
        }

        $prompts = $result->{McpAllowlistProvider::PROMPTS} ?? null;
        if (!\is_array($prompts)) {
            return $responseData;
        }

        $result->{McpAllowlistProvider::PROMPTS} = array_values(
            array_filter(
                $prompts,
                static fn (mixed $prompt): bool => $prompt instanceof \stdClass && \in_array($prompt->name ?? '', $allowlist, true),
            ),
        );

        return $responseData;
    }
}
