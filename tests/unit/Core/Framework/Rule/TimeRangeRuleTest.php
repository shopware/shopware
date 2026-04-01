<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\TimeRangeRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(TimeRangeRule::class)]
class TimeRangeRuleTest extends TestCase
{
    public function testIfOnSameDayInTimeRangeMatches(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('12:00'));

        $match = $rule->match($ruleScope);

        static::assertTrue($match);
    }

    public function testIfOnSameDayInTimeRangeWithTimezoneMatches(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00', 'timezone' => 'Europe/Berlin']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('12:00', new \DateTimeZone('Europe/Berlin')));

        $match = $rule->match($ruleScope);

        static::assertTrue($match);
    }

    public function testIfOnSameDayOutOfTimeRangeMatches(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('12:01'));

        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testIfOnSameDayOutOfTimeRangeWithTimezoneMatches(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00', 'timezone' => 'Europe/Berlin']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('12:01', new \DateTimeZone('Europe/Berlin')));

        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testIfOnSameDayInTimeRangeWithDifferentTimezonesAndCurrentOffsetMatches(): void
    {
        $rule = new TimeRangeRule();

        // Convert the exact instant returned by getCurrentTime() into Berlin to derive toTime.
        // This guarantees the mocked UTC time falls inside [00:00, toTime] in Europe/Berlin,
        // regardless of DST (UTC+1 winter / UTC+2 summer).
        $now = new \DateTimeImmutable('12:00', new \DateTimeZone('UTC'));
        $toTime = $now->setTimezone(new \DateTimeZone('Europe/Berlin'))->modify('+1 hour')->format('H:i');

        $rule->assign(['fromTime' => '00:00', 'toTime' => $toTime, 'timezone' => 'Europe/Berlin']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn($now);

        static::assertTrue($rule->match($ruleScope));
    }

    public function testIfOnSameDayOutOfTimeRangeWithDifferentTimezonesAndCurrentOffsetMatches(): void
    {
        $rule = new TimeRangeRule();

        // toTime is one hour before the local Berlin equivalent of 12:00 UTC,
        // so the mocked UTC time falls outside [00:00, toTime] in Europe/Berlin.
        $now = new \DateTimeImmutable('12:00', new \DateTimeZone('UTC'));
        $toTime = $now->setTimezone(new \DateTimeZone('Europe/Berlin'))->modify('-1 hour')->format('H:i');

        $rule->assign(['fromTime' => '00:00', 'toTime' => $toTime, 'timezone' => 'Europe/Berlin']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn($now);

        static::assertFalse($rule->match($ruleScope));
    }

    public function testIfToTimeIsSmallerThanFromTimeMatchesCorrect(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('23:00'));

        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testBeforeEdgeToNextDayConditionMatchesCorrect(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('22:59'));

        $match = $rule->match($ruleScope);

        static::assertFalse($match);
    }

    public function testOnNextDayConditionMatchesCorrect(): void
    {
        $rule = new TimeRangeRule();

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $ruleScope = $this->createMock(RuleScope::class);
        $ruleScope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('02:46'));

        $match = $rule->match($ruleScope);

        static::assertTrue($match);
    }
}
