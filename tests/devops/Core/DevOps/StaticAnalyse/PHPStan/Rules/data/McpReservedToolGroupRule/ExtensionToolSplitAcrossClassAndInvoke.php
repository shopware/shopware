<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'get_order', description: 'Read an order')]
class ExtensionToolSplitAcrossClassAndInvoke
{
    #[McpToolGroup('discovery')]
    public function __invoke(): string
    {
        return '';
    }
}
