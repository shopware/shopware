<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoNativeTimeClassRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoNativeTimeClassRule>
 */
#[Package('framework')]
class NoNativeTimeClassRuleTest extends RuleTestCase
{
    private const ERROR = 'Do not use native time reads. They cannot be frozen in tests. Use Psr\Clock\ClockInterface to inject a controllable clock.';

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoNativeTimeClassRule/NativeTimeClassUsage.php'], [
            // zero args default to "now"
            [self::ERROR, 11],
            [self::ERROR, 12],

            // explicit "now"
            [self::ERROR, 17],
            [self::ERROR, 18],

            // empty string defaults to "now"
            [self::ERROR, 23],

            // unanchored relative offsets
            [self::ERROR, 28],
            [self::ERROR, 29],
            [self::ERROR, 30],
            [self::ERROR, 31],
            [self::ERROR, 32],

            // time-only (today's date filled in)
            [self::ERROR, 37],

            // named arguments: "now" literal and missing-datetime-only-timezone
            [self::ERROR, 65],
            [self::ERROR, 66],
        ]);
    }

    /**
     * @return NoNativeTimeClassRule
     */
    protected function getRule(): Rule
    {
        // Anonymous subclass disables the path-based exemption that would
        // otherwise silence the rule on fixtures under tests/devops/.../DevOps/.
        return new class extends NoNativeTimeClassRule {
            protected function isPathExempt(Scope $scope): bool
            {
                return false;
            }
        };
    }
}
