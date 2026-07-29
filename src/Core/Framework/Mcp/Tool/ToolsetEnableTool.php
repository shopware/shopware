<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(name: McpToolsetRegistry::ENABLE_TOOLSET_TOOL, title: 'Enable Toolset', description: 'Enable one MCP toolset for the current session and ask the client to refresh tools/list. The toolset remains enabled only for this MCP session.')]
#[McpToolGroup('discovery')]
class ToolsetEnableTool extends McpToolResponse
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

    public function __invoke(string $toolset): string
    {
        $definition = $this->toolsetRegistry->find($toolset);
        if ($definition === null) {
            return $this->error(\sprintf('Unknown MCP toolset "%s". Call %s first to list available toolsets.', $toolset, McpToolsetRegistry::LIST_TOOLSETS_TOOL));
        }

        $request = $this->requestStack->getCurrentRequest();
        $sessionId = $request?->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return $this->error('Cannot enable an MCP toolset without an active MCP session.');
        }

        $this->sessionStorage->enable($sessionId, $toolset);

        // Enabling a toolset only changes the tool list for this session. The controller emits the
        // tools/listChanged after the SDK has saved its session, so that the queued notification is
        // not overwritten; we merely record the intent here (writing it now would be clobbered by
        // the SDK's post-tool session save).
        $request?->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);

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
