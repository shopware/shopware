<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
#[McpToolRequires('order:create')]
class StoreApiToolWithRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
