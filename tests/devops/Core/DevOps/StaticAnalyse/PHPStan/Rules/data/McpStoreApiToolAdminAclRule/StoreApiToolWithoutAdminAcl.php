<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'search_catalog', description: 'Search the catalog')]
class StoreApiToolWithoutAdminAcl
{
    public function __construct(private readonly PrivilegeChecker $checker)
    {
    }

    public function __invoke(): string
    {
        // Not the McpToolResponse helper, so not Admin ACL.
        $this->checker->requirePrivilege('catalog');

        return '';
    }
}
