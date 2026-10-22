<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequiresNoPrivilege;

#[McpTool(name: 'swag-store-hours', description: 'Return the opening hours')]
#[McpToolRequiresNoPrivilege(reason: 'Reads plugin configuration only.')]
class DualScopeToolWithNoPrivilege
{
    public function __invoke(): string
    {
        return '';
    }
}
