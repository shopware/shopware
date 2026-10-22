<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'get_order', description: 'Read an order of the customer')]
class StoreApiToolAliasingPrivilegeTraitMethod extends McpToolResponse
{
    use ChecksOrderAccess, ChecksOrderAccessWithAcl {
        ChecksOrderAccess::check insteadof ChecksOrderAccessWithAcl;
        ChecksOrderAccessWithAcl::check as checkWithAcl;
    }

    public function __invoke(): string
    {
        return $this->checkWithAcl(Context::createDefaultContext()) ?? $this->success([]);
    }
}
