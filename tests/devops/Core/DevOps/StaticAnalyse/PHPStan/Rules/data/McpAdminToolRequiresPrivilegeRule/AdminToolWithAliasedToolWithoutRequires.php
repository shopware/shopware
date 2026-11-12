<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool as Tool;

#[Tool(name: 'swag-export', description: 'Export orders')]
class AdminToolWithAliasedToolWithoutRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
