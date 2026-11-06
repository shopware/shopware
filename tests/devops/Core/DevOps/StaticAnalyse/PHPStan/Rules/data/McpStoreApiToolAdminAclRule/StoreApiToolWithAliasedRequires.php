<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires as Requires;

#[McpTool(name: 'add_to_cart', description: 'Add a product to the cart')]
#[Requires('order:create')]
class StoreApiToolWithAliasedRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
