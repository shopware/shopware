<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: self::NAME, title: 'Tool Search', description: 'Search the allowed Shopware MCP tool catalogue by free-text query and return the most relevant tool definitions inline. Use this when a needed tool is not visible in tools/list.')]
#[Package('framework')]
class ToolSearchTool extends AbstractToolSearchTool
{
}
