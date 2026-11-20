<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'swag-order-read', description: 'Read an order')]
#[McpToolRequires('order:read')]
class AdminToolWithAdminAcl extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->requirePrivilege(Context::createDefaultContext(), 'order:read') ?? $this->success([]);
    }
}
