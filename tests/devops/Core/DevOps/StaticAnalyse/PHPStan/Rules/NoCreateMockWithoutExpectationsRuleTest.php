<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;
use Shopware\Core\Framework\Log\Package;

// the abstract-base fixtures are not autoloadable (their namespace deliberately sits in the rule's
// enabled unit-test namespaces); loading them lets reflection resolve the subclass -> ancestor walk
require_once __DIR__ . '/data/NoCreateMockWithoutExpectationsRule/AbstractBaseCases.php';

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
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'PropertyDependency::class', 'testBare()'),
                51, // mixed property (->expects() in one test, bare in another)
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'PropertyDependency::class', 'testBare()'),
                133, // ->expects()-ing helper reached by one test through a two-hop chain, bare in the other
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'PropertyDependency::class', 'testBare()'),
                196, // forwarding helper cannot cover a test: direct ->expects() in one test, bare in the other
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'PropertyDependency::class', 'PropertyDependency::class'),
                228, // helper only stub-configures the property and forwards it into the SUT constructor
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_ORPHANED, 'PropertyDependency::class', 'testReCreates()'),
                282, // setUp instance orphaned by a test re-creating the property
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_ORPHANED, 'PropertyDependency::class', 'testReCreates()'),
                306, // orphaned only in the re-creating test; the other configures the setUp instance
            ],
            // NOT flagged: 78 (expected in every test), 106 (->expects()-ed via a helper the test calls),
            // 169 (setUp reaches the ->expects()-ing helper, covering every test),
            // 255 (a helper hands the property to a call the rule cannot resolve)
        ]);
    }

    public function testAbstractBaseFixtures(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/AbstractBaseCases.php'], [
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'BaseDependency::class', 'testBare()'),
                24, // inherited property, covered via the inherited helper in one subclass test, bare in the other
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'BaseDependency::class', 'BaseDependency::class'),
                39, // local stub in a base helper, reported when MixedChildCases is analysed
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'BaseDependency::class', 'BaseDependency::class'),
                39, // ... and again when CoveredChildCases is analysed
            ],
            // NOT flagged: line 24 for CoveredChildCases (its only test reaches the inherited
            // ->expects()-ing helper), the abstract class itself (skipped, no runnable instances)
        ]);
    }

    public function testOpaqueUses(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/OpaqueUseCases.php'], [
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'OpaqueDependency::class', 'testBare()'),
                31, // embedded in another double's willReturnMap() by the helper: data, not a hidden expectation
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'OpaqueLocator::class', 'OpaqueLocator::class'),
                32, // the locator double itself is only ever stub-configured
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'OpaqueDependency::class', 'testBare()'),
                83, // created inside the fixture helper: the tests reaching it own an instance
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_MIXED, 'OpaqueDependency::class', 'testBare()'),
                100, // forwarded into the SUT constructor wrapped in an array literal
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'OpaqueDependency::class', 'OpaqueDependency::class'),
                130, // helper re-binds its parameter with `$dep = $dep ?? <stub>` before the SUT constructor
            ],
            [
                \sprintf(NoCreateMockWithoutExpectationsRule::ERROR_STUB, 'OpaqueDependency::class', 'OpaqueDependency::class'),
                155, // aliased into a clean local that only forwards into the SUT constructor
            ],
            // NOT flagged: 182 (the alias itself is ->expects()-ed — conservative skip),
            // and testNotOwning() at 83 (it never reaches the creating helper)
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
        return new NoCreateMockWithoutExpectationsRule(
            new Configuration([
                'allowedUnitTestClassNamespaces' => ['Shopware\\Tests\\Unit\\', 'Shopware\\Tests\\Migration\\'],
                'createMockWithoutExpectationsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\'],
            ]),
            self::getContainer()->getService('defaultAnalysisParser'),
        );
    }
}
