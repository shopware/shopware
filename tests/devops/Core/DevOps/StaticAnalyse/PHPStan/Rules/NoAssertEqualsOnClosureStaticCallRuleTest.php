<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAssertEqualsOnClosureRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAssertEqualsOnClosureStaticCallRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoAssertEqualsOnClosureStaticCallRule>
 */
#[Package('framework')]
class NoAssertEqualsOnClosureStaticCallRuleTest extends RuleTestCase
{
    public function testOnlyGenuineClosuresAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoAssertEqualsOnClosureRule/StaticCalls.php'], [
            // Only the genuine closure comparison is flagged; the `never`-typed
            // and plain-object operands must not be mistaken for closures.
            [NoAssertEqualsOnClosureRule::ERROR_MESSAGE, 17],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertEqualsOnClosureStaticCallRule();
    }
}
