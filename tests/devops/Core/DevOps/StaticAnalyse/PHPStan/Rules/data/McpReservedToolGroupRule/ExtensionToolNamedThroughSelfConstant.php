<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: self::NAME, description: 'Search the catalog')]
#[McpToolGroup(self::GROUP)]
class ExtensionToolNamedThroughSelfConstant
{
    public const NAME = 'swag-search';

    private const GROUP = 'discovery';
}
