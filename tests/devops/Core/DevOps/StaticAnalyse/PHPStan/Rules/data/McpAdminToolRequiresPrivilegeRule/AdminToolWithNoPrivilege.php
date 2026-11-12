<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequiresNoPrivilege;

#[McpTool(name: 'swag-plugin-version', description: 'Return the plugin version')]
#[McpToolRequiresNoPrivilege(reason: 'Reads no shop data.')]
class AdminToolWithNoPrivilege
{
    public function __invoke(): string
    {
        return '';
    }
}
