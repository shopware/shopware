<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'get_order', description: 'Read an order of the customer')]
class StoreApiToolInheritingRequirePrivilege extends AdminOrderToolBase
{
}
