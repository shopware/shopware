<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool as Tool;
use Shopware\Core\Framework\Mcp\Attribute as Mcp;

#[Tool(name: 'swag-stock-note', description: 'Add a stock note')]
#[Mcp\McpToolRequires('product:update')]
class AdminToolWithAliasedRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
