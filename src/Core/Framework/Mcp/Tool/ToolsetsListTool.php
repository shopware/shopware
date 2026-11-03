<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpRequestedToolsetResolver;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List the MCP toolsets and whether each is enabled for the current session, either in the connect URL or with shopware-toolset-enable. Use this when no advertised tool fits the task: a toolset\'s tools become available once you enable it.')]
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
        private readonly McpRequestedToolsetResolver $requestedToolsetResolver,
    ) {
    }

    public function __invoke(): string
    {
        $enabledToolsets = $this->enabledToolsets();
        $areAllToolsetsEnabled = \in_array(McpToolsetRegistry::ALL_TOOLSETS, $enabledToolsets, true);

        return $this->success([
            'toolsets' => array_map(
                static fn (array $toolset): array => [
                    ...$toolset,
                    'enabled' => $areAllToolsetsEnabled || \in_array($toolset['name'], $enabledToolsets, true),
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
        $requestedToolsets = $this->requestedToolsetResolver->resolve();

        $sessionId = $this->requestStack->getCurrentRequest()?->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return $requestedToolsets;
        }

        return [...$requestedToolsets, ...$this->sessionStorage->enabledToolsets($sessionId)];
    }
}
