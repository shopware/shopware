<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;

#[McpTool('swag-report', 'Report', 'Build a report')]
#[McpToolGroup(group: McpToolsetRegistry::DISCOVERY_GROUP)]
class ExtensionToolInDiscoveryViaConstant
{
}
