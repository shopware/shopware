<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAnyInvocationMatcherRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoAnyInvocationMatcherRule>
 */
#[Package('framework')]
class NoAnyInvocationMatcherRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoAnyInvocationMatcherRule/Cases.php'], [
            // expects(static::any()) in a non-TestCase helper → flagged (no inheritance gate, StaticCall branch)
            [NoAnyInvocationMatcherRule::ERROR_REDUNDANT, 38],
            // expects(self::any()) in the same helper → flagged (StaticCall branch)
            [NoAnyInvocationMatcherRule::ERROR_REDUNDANT, 43],
            // expects($this->any()) in a TestCase → flagged (MethodCall branch)
            [NoAnyInvocationMatcherRule::ERROR_REDUNDANT, 60],
            // NOT flagged: 48 (never), 67 (once), 74 (bare method)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAnyInvocationMatcherRule();
    }
}
