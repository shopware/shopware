<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\TimeRangeRule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class TimeRangeRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIfOnSameDayInTimeRangeMatches(): void
    {
        $now = new \DateTimeImmutable('12:00');
        $rule = new TimeRangeRule($now);

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00']);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertTrue($match);
    }

    public function testIfOnSameDayOutOfTimeRangeMatches(): void
    {
        $now = new \DateTimeImmutable('12:01');
        $rule = new TimeRangeRule($now);

        $rule->assign(['fromTime' => '00:00', 'toTime' => '12:00']);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($match);
    }

    public function testIfToTimeIsSmallerThanFromTimeMatchesCorrect(): void
    {
        $now = new \DateTimeImmutable('23:00');
        $rule = new TimeRangeRule($now);

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($match);
    }

    public function testBeforeEdgeToNextDayConditionMatchesCorrect(): void
    {
        $now = new \DateTimeImmutable('22:59');
        $rule = new TimeRangeRule($now);

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($match);
    }

    public function testOnNextDayConditionMatchesCorrect(): void
    {
        $now = new \DateTimeImmutable('02:46');
        $rule = new TimeRangeRule($now);

        $rule->assign(['fromTime' => '23:00', 'toTime' => '22:00']);

        $match = $rule->match($this->createMock(RuleScope::class));

        static::assertTrue($match);
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $conditionRepository = $this->getContainer()->get('rule_condition.repository');

        $ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $conditionRepository->create([
            [
                'id' => $id,
                'type' => (new TimeRangeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'fromTime' => '15:00',
                    'toTime' => '12:00',
                ],
            ],
        ], $context);

        $result = $conditionRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertNotNull($result);
        static::assertEquals('12:00', $result->getValue()['toTime']);
        static::assertEquals('15:00', $result->getValue()['fromTime']);
    }
}
