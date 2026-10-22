<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'get_order', description: 'Read an order of the customer')]
class StoreApiToolUsingPrivilegeTrait extends McpToolResponse
{
    use ChecksAdminPrivileges;

    public function __invoke(): string
    {
        return $this->checkOrderPrivilege(Context::createDefaultContext()) ?? $this->success([]);
    }
}
