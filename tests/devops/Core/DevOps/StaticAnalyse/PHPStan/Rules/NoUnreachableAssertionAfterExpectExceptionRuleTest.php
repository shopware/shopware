<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoUnreachableAssertionAfterExpectExceptionRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoUnreachableAssertionAfterExpectExceptionRule>
 */
#[Package('framework')]
class NoUnreachableAssertionAfterExpectExceptionRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/data/NoUnreachableAssertionAfterExpectExceptionRule/Cases.php',
            __DIR__ . '/data/NoUnreachableAssertionAfterExpectExceptionRule/ForeignNamespaceCases.php',
        ], [
            // UnreachableAssertions::testTrailingAssertNeverRuns: assert after the throwing call
            [NoUnreachableAssertionAfterExpectExceptionRule::ERROR_UNREACHABLE_ASSERTION, 20],
            // UnreachableAssertions::testDeadTailAfterSecondActPhase: dead assert directly after the act call
            [NoUnreachableAssertionAfterExpectExceptionRule::ERROR_UNREACHABLE_ASSERTION, 32],
            // UnreachableAssertions::testDeadTailAfterSecondActPhase: dead assert after a second act phase
            [NoUnreachableAssertionAfterExpectExceptionRule::ERROR_UNREACHABLE_ASSERTION, 36],
            // NOT flagged: assertion before the act call, try/finally, no expectException,
            // assert-named call on another object, conditional expectException, non-TestCase class,
            // and the banned shape in a namespace outside FIRST_PARTY_TEST_NAMESPACES
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoUnreachableAssertionAfterExpectExceptionRule();
    }
}
