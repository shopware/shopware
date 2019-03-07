<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\WeekdayRule;

class WeekdayRuleTest extends TestCase
{
    public function testMatchForCurrentDay(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_EQ,
            'dayOfWeek' => \date('N'),
        ]);
        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertTrue($match->matches());
        static::assertStringStartsWith('Today is not the selected day of week.', $match->getMessages()[0]);
    }

    public function testMatchForYesterday(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_EQ,
            'dayOfWeek' => (new \DateTime())->modify('-1 day')->format('N'),
        ]);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($match->matches());
        static::assertStringStartsWith('Today is not the selected day of week.', $match->getMessages()[0]);
    }

    public function testMatchWithNotEqualsOperator(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_NEQ,
            'dayOfWeek' => \date('N'),
        ]);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($match->matches());
        static::assertStringStartsWith('Today is the selected day of week.', $match->getMessages()[0]);
    }
}
