<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List MCP toolsets that can be enabled for the current session. Use this before enabling additional tools when only the default meta-tools are visible.')]
#[Package('framework')]
class ToolsetsListTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly McpToolsetRegistry $toolsetRegistry,
        private readonly McpToolsetSessionStorage $sessionStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(): string
    {
        $enabledToolsets = $this->enabledToolsets();

        return $this->success([
            'toolsets' => array_map(
                static fn (array $toolset): array => [
                    ...$toolset,
                    'enabled' => $toolset['enabledByDefault'] || \in_array($toolset['name'], $enabledToolsets, true),
                ],
                $this->toolsetRegistry->toolsets(),
            ),
        ], [
            'taxonomy' => 'prefix-fallback',
            'note' => 'Toolsets are inferred from tool name prefixes until curated toolset metadata is available.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function enabledToolsets(): array
    {
        $sessionId = $this->requestStack->getCurrentRequest()?->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return [];
        }

        return $this->sessionStorage->enabledToolsets($sessionId);
    }
}
