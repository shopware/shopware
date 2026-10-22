<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

abstract class AdminOrderToolBase extends McpToolResponse
{
    public function __invoke(): string
    {
        if ($error = $this->requirePrivilege(Context::createDefaultContext(), 'order:read')) {
            return $error;
        }

        return $this->success([]);
    }
}
