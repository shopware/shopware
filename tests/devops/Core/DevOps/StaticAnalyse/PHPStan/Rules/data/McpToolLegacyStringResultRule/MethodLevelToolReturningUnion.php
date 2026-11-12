<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

class MethodLevelToolReturningUnion extends McpToolResponse
{
    public function __invoke(): string
    {
        return 'not a tool, the attribute sits on search()';
    }

    #[McpTool('test-tool', 'A method-level tool returning a union with string')]
    public function search(): McpToolResult|string
    {
        return '{"success":true,"data":[]}';
    }
}
