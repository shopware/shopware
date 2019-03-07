<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\TimeRangeRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TimeRangeRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TimeRangeRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new TimeRangeRule();
    }

    public function testIfOnSameDayInTimeRangeMatches(): void
    {
        $this->rule->assign(['toTime' => '12:00', 'fromTime' => '00:00']);
        $match = $this->rule->match($this->createMock(RuleScope::class));
        if ((int) date('H') > 12) {
            static::assertFalse($match->matches());
        } else {
            static::assertTrue($match->matches());
        }
    }

    public function testIfToTimeIsSmallerThanFromTimeMatchesCorrect(): void
    {
        $this->rule->assign(['toTime' => '22:00', 'fromTime' => '23:00']);

        $match = $this->rule->match($this->createMock(RuleScope::class));
        if ((int) date('H') > 22 && (int) date('H') < 23) {
            static::assertFalse($match->matches());
        } else {
            static::assertTrue($match->matches());
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $conditionRepository = $this->getContainer()->get('rule_condition.repository');

        $ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::uuid4()->getHex();
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

        /* @var RuleConditionEntity $result */
        $result = $conditionRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertNotNull($result);
        static::assertEquals('12:00', $result->getValue()['toTime']);
        static::assertEquals('15:00', $result->getValue()['fromTime']);
    }
}
