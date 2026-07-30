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
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                125, // fed only to an inherited assertion, which cannot configure expectations
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'Dependency::class', 'Dependency::class'),
                154, // handed back to the caller, whose only use is SUT constructor forwarding
            ],
            // NOT flagged: 55 (->expects), 66 (helper ->expects it), 85 (inline ->expects),
            // 116 (helper parks it on a property), 135 (->expects()-ed through a fixture alias)
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
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'PropertyDependency::class', 'PropertyDependency::class'),
                132, // helper only stub-configures the property and forwards it into the SUT constructor
            ],
            // NOT flagged: 78 (expected in every test), 105 (->expects()-ed via a helper),
            // 159 (a helper hands the property to a call the rule cannot resolve)
        ]);
    }

    public function testHelperReturnedMocks(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/HelperReturnCases.php'], [
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'ReturnDependency::class', 'ReturnDependency::class'),
                27, // helper returns the double directly, no call site expects it
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'ReturnDependency::class', 'ReturnDependency::class'),
                47, // helper stub-configures and bare-returns, callers stay clean
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'ReturnDependency::class', 'ReturnDependency::class'),
                129, // the double only feeds an inherited assertion, which cannot configure expectations
            ],
            // NOT flagged: 74 (chained ->expects() on the helper result), 95 (bound result
            // ->expects()-ed later), 115 (result handed to an unresolvable call), 146 (an
            // assert-named method of this class configures an expectation)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule();
    }
}
