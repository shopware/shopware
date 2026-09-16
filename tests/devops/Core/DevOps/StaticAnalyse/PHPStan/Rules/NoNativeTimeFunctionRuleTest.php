<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoNativeTimeFunctionRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoNativeTimeFunctionRule>
 */
#[Package('framework')]
class NoNativeTimeFunctionRuleTest extends RuleTestCase
{
    private const ERROR = 'Do not use native time reads. They cannot be frozen in tests. Use Psr\Clock\ClockInterface to inject a controllable clock.';

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoNativeTimeFunctionRule/NativeTimeFunctionUsage.php'], [
            // time() / microtime() / hrtime() — always flagged
            [self::ERROR, 11],
            [self::ERROR, 12],
            [self::ERROR, 13],
            [self::ERROR, 14],

            // strtotime with a literal string that reads current time
            [self::ERROR, 19],
            [self::ERROR, 20],
            [self::ERROR, 21],
            [self::ERROR, 22],
            [self::ERROR, 23],

            // strtotime with literal null base — equivalent to default
            [self::ERROR, 29],

            // bare microtime() with no args
            [self::ERROR, 67],

            // strtotime named args: datetime-only "+1 day", and null base + relative
            [self::ERROR, 79],
            [self::ERROR, 81],

            // date_create / date_create_immutable — same policy as new DateTime*
            [self::ERROR, 88],
            [self::ERROR, 89],
            [self::ERROR, 92],
            [self::ERROR, 93],
            [self::ERROR, 96],
            [self::ERROR, 97],
        ]);
    }

    /**
     * @return NoNativeTimeFunctionRule
     */
    protected function getRule(): Rule
    {
        // Anonymous subclass disables the path-based exemption that would
        // otherwise silence the rule on fixtures under tests/devops/.../DevOps/.
        return new class($this->createReflectionProvider()) extends NoNativeTimeFunctionRule {
            protected function isPathExempt(Scope $scope): bool
            {
                return false;
            }
        };
    }
}
