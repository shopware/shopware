<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

class MethodLevelToolReturningMixed extends McpToolResponse
{
    #[McpTool('test-tool', 'A tool returning mixed')]
    public function search(): mixed
    {
        return '{"success":true,"data":[]}';
    }
}
