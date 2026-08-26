<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Store API variant of {@see ToolsetEnableTool}. A distinct concrete class is required because the
 * MCP SDK binds a tool to the class carrying #[McpTool] and the store-api service locator keys on
 * the service id (= class). It is wired with the store-api toolset registry, session storage and
 * the store-api listChanged notifier, so enabling a toolset only refreshes store-api sessions.
 */
#[Package('framework')]
#[McpTool(name: McpToolsetRegistry::ENABLE_TOOLSET_TOOL, title: 'Enable Toolset', description: 'Enable one MCP toolset for the current session and ask the client to refresh tools/list. The toolset remains enabled only for this MCP session.')]
#[McpToolGroup('discovery')]
class StoreApiToolsetEnableTool extends ToolsetEnableTool
{
    #[\Override]
    public function __invoke(string $toolset): string
    {
        return parent::__invoke($toolset);
    }
}
