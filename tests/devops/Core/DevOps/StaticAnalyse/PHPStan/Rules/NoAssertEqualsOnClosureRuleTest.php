<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAssertEqualsOnClosureRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoAssertEqualsOnClosureRule>
 */
#[Package('framework')]
class NoAssertEqualsOnClosureRuleTest extends RuleTestCase
{
    public function testStaticCallsOnlyReportGenuineClosures(): void
    {
        $this->analyse([__DIR__ . '/data/NoAssertEqualsOnClosureRule/StaticCalls.php'], [
            // Only the genuine closure comparison is flagged; the `never`-typed
            // and plain-object operands must not be mistaken for closures.
            [NoAssertEqualsOnClosureRule::ERROR_MESSAGE, 17],
        ]);
    }

    public function testInstanceCallsAreCoveredAndGatedOnTestCaseReceiver(): void
    {
        $this->analyse([__DIR__ . '/data/NoAssertEqualsOnClosureRule/MethodCalls.php'], [
            // The instance call on a TestCase receiver is flagged; the same comparison
            // through a non-TestCase receiver is left alone.
            [NoAssertEqualsOnClosureRule::ERROR_MESSAGE, 21],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertEqualsOnClosureRule();
    }
}
