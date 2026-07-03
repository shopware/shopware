<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: McpToolsetRegistry::ENABLE_TOOLSET_TOOL, title: 'Enable Toolset', description: 'Enable one MCP toolset for the current session and ask the client to refresh tools/list. The toolset remains enabled only for this MCP session.', meta: ['deferred' => false])]
#[McpToolGroup('default')]
#[Package('framework')]
class ToolsetEnableTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly McpToolsetRegistry $toolsetRegistry,
        private readonly McpToolsetSessionStorage $sessionStorage,
        private readonly McpListChangedNotifier $notifier,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(string $toolset): string
    {
        $definition = $this->toolsetRegistry->find($toolset);
        if ($definition === null) {
            return $this->error(\sprintf('Unknown MCP toolset "%s". Call %s first to list available toolsets.', $toolset, McpToolsetRegistry::LIST_TOOLSETS_TOOL));
        }

        $sessionId = $this->requestStack->getCurrentRequest()?->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return $this->error('Cannot enable an MCP toolset without an active MCP session.');
        }

        $this->sessionStorage->enable($sessionId, $toolset);

        $this->notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        return $this->success([
            'toolset' => [
                ...$definition,
                'enabled' => true,
            ],
        ], [
            'listChanged' => true,
        ]);
    }
}
