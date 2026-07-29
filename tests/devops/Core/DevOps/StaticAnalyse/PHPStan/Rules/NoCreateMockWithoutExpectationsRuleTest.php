<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoCreateMockWithoutExpectationsRule>
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/Cases.php'], [
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                44, // local stub
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                77, // inline stub passed into the SUT
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                95, // stub forwarded into the SUT by a fixture helper
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                106, // stub forwarded into the SUT through two fixture helpers
            ],
            // NOT flagged: 55 (->expects), 66 (helper ->expects it), 85 (inline ->expects),
            // 115 (helper parks it on a property), 124 (callee not declared in this class),
            // 134 (->expects()-ed through a fixture alias), 152 (handed back to the caller)
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        // A clear stub in a namespace not yet in the allowlist must produce no error.
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/CasesOutOfScope.php'], []);
    }

    public function testPropertyMocks(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/PropertyCases.php'], [
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'PropertyDependency::class', 'PropertyDependency::class'),
                23, // pure-stub property (never ->expects() in the class)
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'PropertyDependency::class'),
                51, // mixed property (->expects() in one test, bare in another)
            ],
            // NOT flagged: 78 (expected in every test), 105 (configured via a helper)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule();
    }
}
