<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
#[McpToolGroup('discovery')]
class ExtensionToolInDiscovery
{
}
