<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
#[McpTool(name: self::NAME, title: 'Tool Search', description: 'Search the Store API MCP tool catalogue by free-text query and return the most relevant tool definitions inline. Use this when a needed tool is not visible in tools/list.')]
#[McpToolGroup('discovery')]
class StoreApiToolSearchTool extends AbstractToolSearchTool
{
    /**
     * Re-declares the inherited handler on the concrete class so the MCP SDK discoverer
     * binds the tool to this class instead of the (non-instantiable) abstract base.
     */
    #[\Override]
    public function __invoke(string $query, int $maxResults = 3): string
    {
        return parent::__invoke($query, $maxResults);
    }

    #[\Override]
    protected function usageHint(): ?string
    {
        return 'A matched tool may not be advertised in tools/list yet. If your MCP client cannot call it '
            . 'directly from this result, run shopware-toolsets-list to find the toolset that contains it, '
            . 'enable that toolset with shopware-toolset-enable, then call the tool. Enabling a toolset emits '
            . 'a tools/listChanged notification so the client refreshes tools/list.';
    }
}
