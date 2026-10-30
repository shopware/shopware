<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\WeekdayRule;
use Symfony\Component\Validator\Constraints\Timezone;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(WeekdayRule::class)]
class WeekdayRuleTest extends TestCase
{
    public function testMatchForCurrentDay(): void
    {
        $rule = new WeekdayRule();
        $rule->assign([
            'operator' => WeekdayRule::OPERATOR_EQ,
            'dayOfWeek' => (int) date('N'),
        ]);

        $ruleScope = static::createStub(RuleScope::class);
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

        $ruleScope = static::createStub(RuleScope::class);
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

        $ruleScope = static::createStub(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable());
        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testMatchUsesTimezoneWhenDayDiffersFromUtc(): void
    {
        // Monday 23:30 UTC is already Tuesday in Berlin
        $rule = new WeekdayRule(WeekdayRule::OPERATOR_EQ, 2, 'Europe/Berlin');

        static::assertTrue($rule->match($this->createScope('2024-01-01 23:30:00', 'UTC')));
    }

    public function testNoMatchForUtcDayWhenTimezoneShiftsDay(): void
    {
        $rule = new WeekdayRule(WeekdayRule::OPERATOR_EQ, 1, 'Europe/Berlin');

        static::assertFalse($rule->match($this->createScope('2024-01-01 23:30:00', 'UTC')));
    }

    public function testMatchWithoutTimezoneUsesScopeTime(): void
    {
        $rule = new WeekdayRule(WeekdayRule::OPERATOR_EQ, 1);

        static::assertTrue($rule->match($this->createScope('2024-01-01 23:30:00', 'UTC')));
    }

    public function testMatchWithTimezoneBehindUtc(): void
    {
        // Tuesday 01:00 UTC is still Monday in New York
        $rule = new WeekdayRule(WeekdayRule::OPERATOR_EQ, 1, 'America/New_York');

        static::assertTrue($rule->match($this->createScope('2024-01-02 01:00:00', 'UTC')));
    }

    public function testConstraints(): void
    {
        $constraints = (new WeekdayRule())->getConstraints();

        static::assertArrayHasKey('timezone', $constraints);
        static::assertEquals([new Timezone()], $constraints['timezone']);
    }

    public function testConfigContainsTimezoneField(): void
    {
        $fields = (new WeekdayRule())->getConfig()->getData()['fields'];

        static::assertArrayHasKey('timezone', $fields);
        static::assertSame('string', $fields['timezone']['type']);
    }

    private function createScope(string $time, string $timezone): RuleScope
    {
        $ruleScope = static::createStub(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable($time, new \DateTimeZone($timezone)));

        return $ruleScope;
    }
}
