<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpToolResponseRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<McpToolResponseRule>
 */
#[Package('framework')]
class McpToolResponseTraitRuleTest extends RuleTestCase
{
    public function testToolExtendingAbstractClassPasses(): void
    {
        $this->analyse([
            __DIR__ . '/data/McpToolResponseTraitRule/ToolWithTrait.php',
        ], []);
    }

    public function testToolExtendingAbstractMcpToolResponsePasses(): void
    {
        $this->analyse([
            __DIR__ . '/data/McpToolResponseTraitRule/ToolWithAbstractResponse.php',
        ], []);
    }

    public function testToolWithoutExtendsFails(): void
    {
        $this->analyse([
            __DIR__ . '/data/McpToolResponseTraitRule/ToolWithoutTrait.php',
        ], [[
            'MCP tools with #[McpTool] attribute must extend McpToolResponse.',
            7,
        ]]);
    }

    public function testClassWithoutAttributePasses(): void
    {
        $this->analyse([
            __DIR__ . '/data/McpToolResponseTraitRule/NotATool.php',
        ], []);
    }

    protected function getRule(): Rule
    {
        return new McpToolResponseRule(self::createReflectionProvider());
    }
}
