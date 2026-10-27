<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Mcp\Capability\Attribute\McpTool;

#[McpTool('test-tool', 'A tool inheriting an invoke that returns the result object')]
class ToolInheritingResultInvoke extends AbstractResultTool
{
}
