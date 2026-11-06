<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'swag-test-double', description: 'A tool no container knows')]
class UnregisteredToolWithoutRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
