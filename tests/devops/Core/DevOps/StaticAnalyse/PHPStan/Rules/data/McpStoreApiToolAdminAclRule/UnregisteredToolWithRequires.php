<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'swag-test-double', description: 'A tool no container knows')]
#[McpToolRequires('order:read')]
class UnregisteredToolWithRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
