<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'get_order', description: 'Read an order of the customer')]
class StoreApiToolCallingRequirePrivilege extends McpToolResponse
{
    public function __invoke(): string
    {
        $context = Context::createDefaultContext();
        if ($error = $this->requirePrivilege($context, 'order:read')) {
            return $error;
        }

        return $this->success([]);
    }
}
