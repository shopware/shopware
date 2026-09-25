<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpReservedToolGroupRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<McpReservedToolGroupRule>
 */
#[Package('framework')]
class McpReservedToolGroupRuleTest extends RuleTestCase
{
    public function testExtensionToolInTheDiscoveryGroupFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpReservedToolGroupRule/ExtensionToolInDiscovery.php'], [[
            'MCP tool "create_cart" must not use the reserved "discovery" group, which is limited to the core discovery tools. Give it a group of its own and select that toolset at connect time with ?toolsets=.',
            8,
        ]]);
    }

    public function testDiscoveryGroupNamedThroughTheConstantFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpReservedToolGroupRule/ExtensionToolInDiscoveryViaConstant.php'], [[
            'MCP tool "swag-report" must not use the reserved "discovery" group, which is limited to the core discovery tools. Give it a group of its own and select that toolset at connect time with ?toolsets=.',
            9,
        ]]);
    }

    public function testCoreDiscoveryToolPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpReservedToolGroupRule/CoreMetaToolInDiscovery.php'], []);
    }

    public function testToolInItsOwnGroupPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpReservedToolGroupRule/ExtensionToolInOwnGroup.php'], []);
    }

    protected function getRule(): Rule
    {
        return new McpReservedToolGroupRule();
    }
}
