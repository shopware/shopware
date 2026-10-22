<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;

#[McpTool(name: 'get_order', description: 'Read an order of the customer')]
class StoreApiToolShadowingPrivateCheck extends AdminOrderToolWithPrivateCheckBase
{
    /**
     * Never called: the parent's __invoke() keeps calling its own private checkOrderPrivilege().
     */
    private function checkOrderPrivilege(Context $context): ?string
    {
        return null;
    }
}
