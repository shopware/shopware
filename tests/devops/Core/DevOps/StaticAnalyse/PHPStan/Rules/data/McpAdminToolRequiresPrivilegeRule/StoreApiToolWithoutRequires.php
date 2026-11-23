<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
class StoreApiToolWithoutRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
