<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpProtocolEra;
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
        $request = $this->requestStack->getCurrentRequest();
        $sessionId = $request?->headers->get('Mcp-Session-Id') ?? '';

        // Not advertised on the stateless era (2026-07-28), which has no session to keep the toolset
        // in. A client that kept the name from a handshake connection is pointed to `?toolsets=`.
        if ($sessionId === '' || McpProtocolEra::isStateless($this->requestStack->getMainRequest())) {
            return $this->error(McpProtocolEra::toolsetToolUnavailableMessage(McpToolsetRegistry::ENABLE_TOOLSET_TOOL, $toolset));
        }

        $definition = $this->toolsetRegistry->find($toolset);
        if ($definition === null) {
            return $this->error(\sprintf('Unknown MCP toolset "%s". Call %s first to list available toolsets.', $toolset, McpToolsetRegistry::LIST_TOOLSETS_TOOL));
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
