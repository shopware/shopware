<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'list_wishlist', description: 'List the wishlist of the customer')]
class StoreApiToolWithAnonymousPrivilegeHelper
{
    public function __invoke(): string
    {
        // `$this` inside the anonymous class is the helper, not the tool.
        $helper = new class {
            public function check(): void
            {
                $this->requirePrivilege('wishlist');
            }

            private function requirePrivilege(string $privilege): void
            {
            }
        };
        $helper->check();

        return '';
    }
}
