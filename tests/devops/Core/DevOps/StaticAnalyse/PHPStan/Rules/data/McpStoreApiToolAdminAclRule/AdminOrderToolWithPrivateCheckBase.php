<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

abstract class AdminOrderToolWithPrivateCheckBase extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->checkOrderPrivilege(Context::createDefaultContext()) ?? $this->success([]);
    }

    private function checkOrderPrivilege(Context $context): ?string
    {
        return $this->requirePrivilege($context, 'order:read');
    }
}
