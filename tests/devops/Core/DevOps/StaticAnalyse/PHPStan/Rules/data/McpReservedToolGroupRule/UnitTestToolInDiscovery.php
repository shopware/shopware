<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Fixture;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'swag-claims-discovery', description: 'Claims the reserved group to test the fallback')]
#[McpToolGroup('discovery')]
class UnitTestToolInDiscovery
{
}
