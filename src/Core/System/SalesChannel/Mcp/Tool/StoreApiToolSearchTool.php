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
#[McpTool(name: self::NAME, title: 'Tool Search', description: 'Search the Store API MCP tool catalogue by free-text query and return the most relevant tool definitions inline.', meta: ['deferred' => false])]
#[McpToolGroup('store-api')]
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
}
