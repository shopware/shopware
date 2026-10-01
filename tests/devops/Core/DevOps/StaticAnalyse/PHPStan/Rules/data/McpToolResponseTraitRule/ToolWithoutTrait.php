<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolResponseTraitRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool('test-tool', 'A tool missing the response trait')]
class ToolWithoutTrait
{
    public function __invoke(): string
    {
        return '{}';
    }
}
