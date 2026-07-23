<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Tool\ToolsetsListTool;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Store API variant of {@see ToolsetsListTool}. A distinct concrete class is required because the
 * MCP SDK binds a tool to the class carrying #[McpTool] and the store-api service locator keys on
 * the service id (= class). It is wired with the store-api toolset registry + session storage.
 */
#[Package('framework')]
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List MCP toolsets that can be enabled for the current session. Use this first for any task: no domain tools are advertised until you enable their toolset.')]
#[McpToolGroup('discovery')]
class StoreApiToolsetsListTool extends ToolsetsListTool
{
    #[\Override]
    public function __invoke(): string
    {
        return parent::__invoke();
    }
}
