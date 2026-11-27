<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequiresNoPrivilege;

#[McpTool(name: 'swag-order-archive', description: 'Archive an order')]
#[McpToolRequires('order:update')]
#[McpToolRequiresNoPrivilege]
class AdminToolWithRequiresAndNoPrivilege
{
    public function __invoke(): string
    {
        return '';
    }
}
