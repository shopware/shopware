<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpToolLegacyStringResultRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule\MethodLevelToolReturningUnion;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule\ToolReturningNullableString;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule\ToolReturningString;

/**
 * @internal
 *
 * @extends RuleTestCase<McpToolLegacyStringResultRule>
 */
#[Package('framework')]
class McpToolLegacyStringResultRuleTest extends RuleTestCase
{
    private const TIP = 'The legacy {"success": …} string is deprecated and no longer supported with 6.8.0.';

    public function testToolReturningTheResultObjectPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpToolLegacyStringResultRule/ToolReturningResult.php'], []);
    }

    public function testClassWithoutAttributePasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpToolLegacyStringResultRule/NotATool.php'], []);
    }

    public function testInvokeOfAClassLevelToolReturningAStringIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/McpToolLegacyStringResultRule/ToolReturningString.php'], [
            [self::message(ToolReturningString::class, '__invoke'), 11, self::TIP],
        ]);
    }

    public function testNullableStringIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/McpToolLegacyStringResultRule/ToolReturningNullableString.php'], [
            [self::message(ToolReturningNullableString::class, '__invoke'), 11, self::TIP],
        ]);
    }

    public function testMethodCarryingTheAttributeIsReportedForAUnionWithString(): void
    {
        $this->analyse([__DIR__ . '/data/McpToolLegacyStringResultRule/MethodLevelToolReturningUnion.php'], [
            [self::message(MethodLevelToolReturningUnion::class, 'search'), 16, self::TIP],
        ]);
    }

    protected function getRule(): Rule
    {
        return new McpToolLegacyStringResultRule();
    }

    private static function message(string $class, string $method): string
    {
        return \sprintf(
            'MCP tool method %s::%s() must return McpToolResult, built with successResult() or errorResult(), instead of a hand-built result string.',
            $class,
            $method,
        );
    }
}
