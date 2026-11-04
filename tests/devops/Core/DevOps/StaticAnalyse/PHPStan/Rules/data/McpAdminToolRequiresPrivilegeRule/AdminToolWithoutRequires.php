<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'swag-report', description: 'Build a sales report')]
class AdminToolWithoutRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
