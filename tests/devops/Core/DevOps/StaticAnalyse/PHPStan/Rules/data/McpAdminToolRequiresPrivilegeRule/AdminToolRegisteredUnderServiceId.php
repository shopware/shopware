<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'swag-stock', description: 'Read the stock of a product')]
class AdminToolRegisteredUnderServiceId
{
    public function __invoke(): string
    {
        return '';
    }
}
