<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\McpAdminToolRequiresPrivilegeRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<McpAdminToolRequiresPrivilegeRule>
 */
#[Package('framework')]
class McpAdminToolRequiresPrivilegeRuleTest extends RuleTestCase
{
    private const FIXTURE_NAMESPACE = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule\\';

    private const TIP = 'Both attributes are informational. Keep enforcing the privileges with requirePrivilege() and the DAL.';

    public function testAdminToolWithoutRequiresFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithoutRequires.php'], [
            [$this->message('AdminToolWithoutRequires'), 7, self::TIP],
        ]);
    }

    public function testAliasedToolAttributeWithoutRequiresFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithAliasedToolWithoutRequires.php'], [
            [$this->message('AdminToolWithAliasedToolWithoutRequires'), 7, self::TIP],
        ]);
    }

    public function testAliasedRequiresAttributePasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithAliasedRequires.php'], []);
    }

    public function testToolDeclaredOnInvokeFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithoutRequiresOnInvoke.php'], [
            [$this->message('AdminToolWithoutRequiresOnInvoke'), 7, self::TIP],
        ]);
    }

    public function testToolRegisteredUnderAnotherServiceIdFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolRegisteredUnderServiceId.php'], [
            [$this->message('AdminToolRegisteredUnderServiceId'), 7, self::TIP],
        ]);
    }

    public function testAdminToolWithRequiresPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithRequires.php'], []);
    }

    public function testStoreApiToolIsNotChecked(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/StoreApiToolWithoutRequires.php'], []);
    }

    public function testToolUnknownToTheContainerIsNotChecked(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/UnregisteredToolWithoutRequires.php'], []);
    }

    public function testAdminToolWithNoPrivilegeOptOutPasses(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithNoPrivilege.php'], []);
    }

    public function testAdminToolWithRequiresAndNoPrivilegeOptOutFails(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/AdminToolWithRequiresAndNoPrivilege.php'], [[
            'Admin API MCP tool ' . self::FIXTURE_NAMESPACE . 'AdminToolWithRequiresAndNoPrivilege must not declare both #[McpToolRequires] and #[McpToolRequiresNoPrivilege]. Keep the one that is true.',
            11,
        ]]);
    }

    public function testToolRegisteredOnBothServersIsCheckedAsAdminApiTool(): void
    {
        $this->analyse([__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/DualScopeToolWithoutRequires.php'], [
            [$this->message('DualScopeToolWithoutRequires'), 7, self::TIP],
        ]);
    }

    public function testCoreToolWithNoPrivilegeOptOutPasses(): void
    {
        $this->analyse([__DIR__ . '/../../../../../../../src/Core/Framework/Mcp/Tool/EntitySchemaTool.php'], []);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(__DIR__ . '/data/McpAdminToolRequiresPrivilegeRule/services.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new McpAdminToolRequiresPrivilegeRule($factory->create());
    }

    private function message(string $class): string
    {
        return \sprintf(
            'Admin API MCP tool %s%s must declare the ACL privileges it needs with #[McpToolRequires] on the class, or #[McpToolRequiresNoPrivilege] if it needs none. Operators and the integration allowlist read them from there.',
            self::FIXTURE_NAMESPACE,
            $class,
        );
    }
}
