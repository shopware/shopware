<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'get_invoice', description: 'Read an invoice of the customer')]
class StoreApiToolCallingRequirePrivilegeInClosure extends McpToolResponse
{
    public function __invoke(): string
    {
        // A non-static closure binds `$this` to the tool.
        $check = fn (Context $context): ?string => $this->requirePrivilege($context, 'document:read');

        return $check(Context::createDefaultContext()) ?? $this->success([]);
    }
}
