<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpStoreApiToolAdminAclRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<McpStoreApiToolAdminAclRule>
 */
#[Package('framework')]
class McpStoreApiToolAdminAclRuleTest extends RuleTestCase
{
    private const FIXTURE_NAMESPACE = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule\\';

    private const TIP = 'Store API tools are protected by the sales channel context and the Store API routes they call.';

    public function testStoreApiToolWithRequiresFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolWithRequires.php'], [[
            'Store API MCP tool ' . self::FIXTURE_NAMESPACE . 'StoreApiToolWithRequires must not declare #[McpToolRequires]. Admin ACL privileges do not apply in a sales channel context.',
            9,
            self::TIP,
        ]]);
    }

    public function testStoreApiToolWithAliasedRequiresFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolWithAliasedRequires.php'], [[
            'Store API MCP tool ' . self::FIXTURE_NAMESPACE . 'StoreApiToolWithAliasedRequires must not declare #[McpToolRequires]. Admin ACL privileges do not apply in a sales channel context.',
            9,
            self::TIP,
        ]]);
    }

    public function testStoreApiToolCallingRequirePrivilegeFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolCallingRequirePrivilege.php'], [[
            'Store API MCP tool ' . self::FIXTURE_NAMESPACE . 'StoreApiToolCallingRequirePrivilege must not call requirePrivilege(). Admin ACL privileges do not apply in a sales channel context.',
            15,
            self::TIP,
        ]]);
    }

    public function testStoreApiToolWithoutAdminAclPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolWithoutAdminAcl.php'], []);
    }

    public function testAdminToolIsNotChecked(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/AdminToolWithAdminAcl.php'], []);
    }

    public function testStoreApiToolInheritingRequirePrivilegeFromParentClassFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolInheritingRequirePrivilege.php'], [[
            $this->inheritedMessage('StoreApiToolInheritingRequirePrivilege', 'AdminOrderToolBase::__invoke'),
            7,
            self::TIP,
        ]]);
    }

    public function testStoreApiToolOverridingTheInheritedMethodPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolOverridingInheritedInvoke.php'], []);
    }

    public function testStoreApiToolUsingTraitThatCallsRequirePrivilegeFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/StoreApiToolUsingPrivilegeTrait.php'], [[
            $this->inheritedMessage('StoreApiToolUsingPrivilegeTrait', 'ChecksAdminPrivileges::checkOrderPrivilege'),
            9,
            self::TIP,
        ]]);
    }

    public function testToolRegisteredOnBothServersIsCheckedAsStoreApiTool(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/DualScopeToolWithRequires.php'], [[
            'Store API MCP tool ' . self::FIXTURE_NAMESPACE . 'DualScopeToolWithRequires must not declare #[McpToolRequires]. Admin ACL privileges do not apply in a sales channel context.',
            9,
            self::TIP,
        ]]);
    }

    public function testToolRegisteredOnBothServersWithNoPrivilegeOptOutPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/DualScopeToolWithNoPrivilege.php'], []);
    }

    public function testToolUnknownToTheContainerIsNotChecked(): void
    {
        $this->analyse([__DIR__ . '/data/McpStoreApiToolAdminAclRule/UnregisteredToolWithRequires.php'], []);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/McpStoreApiToolAdminAclRule/services.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new McpStoreApiToolAdminAclRule($factory->create(), self::getContainer()->getService('defaultAnalysisParser'));
    }

    private function inheritedMessage(string $class, string $method): string
    {
        return \sprintf(
            'Store API MCP tool %s%s must not call requirePrivilege(), but inherits a call from %s%s(). Admin ACL privileges do not apply in a sales channel context.',
            self::FIXTURE_NAMESPACE,
            $class,
            self::FIXTURE_NAMESPACE,
            $method,
        );
    }
}
