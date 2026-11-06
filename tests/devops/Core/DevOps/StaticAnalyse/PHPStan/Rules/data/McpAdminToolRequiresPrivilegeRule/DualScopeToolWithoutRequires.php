<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'swag-shipping-info', description: 'Return shipping information')]
class DualScopeToolWithoutRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
