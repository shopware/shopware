<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\TimeRangeRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('services-settings')]
class TimeRangeRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $ruleRepository = $this->getContainer()->get('rule.repository');
        /** @var EntityRepository<RuleConditionCollection> $conditionRepository */
        $conditionRepository = $this->getContainer()->get('rule_condition.repository');

        $ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $context
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

        $result = $conditionRepository->search(new Criteria([$id]), $context)
            ->getEntities()
            ->get($id);

        static::assertInstanceOf(RuleConditionEntity::class, $result);
        $value = $result->getValue();
        static::assertIsArray($value);
        static::assertArrayHasKey('toTime', $value);
        static::assertArrayHasKey('fromTime', $value);
        static::assertEquals('12:00', $value['toTime']);
        static::assertEquals('15:00', $value['fromTime']);

        $ruleRepository->delete([['id' => $ruleId]], $context);
        $conditionRepository->delete([['id' => $id]], $context);
    }
}
