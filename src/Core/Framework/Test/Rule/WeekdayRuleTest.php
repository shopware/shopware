<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\WeekdayRule;

/**
 * @internal
 */
#[Package('business-ops')]
class WeekdayRuleTest extends TestCase
{
    public function testMatchForCurrentDay(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_EQ,
            'dayOfWeek' => (int) date('N'),
        ]);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable());
        $match = $rule->match($ruleScope);

        static::assertTrue($match);
    }

    public function testMatchForYesterday(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_EQ,
            'dayOfWeek' => (int) (new \DateTime())->modify('-1 day')->format('N'),
        ]);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable());
        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testMatchWithNotEqualsOperator(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_NEQ,
            'dayOfWeek' => (int) date('N'),
        ]);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable());
        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }
}
