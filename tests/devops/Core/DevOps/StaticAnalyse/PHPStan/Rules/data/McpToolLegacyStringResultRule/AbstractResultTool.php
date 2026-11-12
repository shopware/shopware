<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

abstract class AbstractResultTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return $this->successResult([]);
    }
}
