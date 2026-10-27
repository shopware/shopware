<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool('test-tool', 'A tool returning the result object')]
class ToolReturningResult extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return $this->successResult($this->label());
    }

    private function label(): string
    {
        return 'label';
    }
}
