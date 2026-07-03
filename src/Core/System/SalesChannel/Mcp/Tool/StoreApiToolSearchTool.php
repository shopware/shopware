<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 */
#[McpTool(name: self::NAME, title: 'Tool Search', description: 'Search the Store API MCP tool catalogue by free-text query and return the most relevant tool definitions inline.')]
#[Package('framework')]
class StoreApiToolSearchTool extends AbstractToolSearchTool
{
}
