<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

class AdminToolWithoutRequiresOnInvoke
{
    #[McpTool(name: 'swag-export', description: 'Export orders')]
    public function __invoke(): string
    {
        return '';
    }
}
