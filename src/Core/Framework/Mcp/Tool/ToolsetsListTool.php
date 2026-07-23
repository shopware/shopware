<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List MCP toolsets that can be enabled for the current session. Use this first for any task: no domain tools are advertised until you enable their toolset.')]
#[McpToolGroup('discovery')]
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
                    'enabled' => \in_array($toolset['name'], $enabledToolsets, true),
                ],
                $this->toolsetRegistry->toolsets(),
            ),
        ], [
            'taxonomy' => 'tool-groups',
            'note' => 'Toolsets are derived from explicit MCP tool group metadata.',
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
