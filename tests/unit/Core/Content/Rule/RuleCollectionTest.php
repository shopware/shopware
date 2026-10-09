<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function testGetIdsByArea(): void
    {
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['b', 'c']);

        $ruleC = new RuleEntity();
        $ruleC->setId(Uuid::randomHex());
        $ruleC->setAreas(['c']);

        $ruleD = new RuleEntity();
        $ruleD->setId(Uuid::randomHex());

        $ruleE = new RuleEntity();
        $ruleE->setId(Uuid::randomHex());
        $ruleE->setAreas(['a', 'd']);

        $collection = new RuleCollection([$ruleA, $ruleB, $ruleC, $ruleD, $ruleE]);

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleE->getId()],
            'b' => [$ruleA->getId(), $ruleB->getId()],
            'c' => [$ruleB->getId(), $ruleC->getId()],
            'd' => [$ruleE->getId()],
        ], $collection->getIdsByArea());
    }

    public function testGetIdsByAreaDeduplicatesAndKeepsInsertionOrder(): void
    {
        // a rule listing the same area twice must not produce a duplicate id
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['a']);

        $collection = new RuleCollection([$ruleA, $ruleB]);

        $result = $collection->getIdsByArea();

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleB->getId()],
            'b' => [$ruleA->getId()],
        ], $result);

        // the returned id lists must be sequentially indexed (real lists, no gaps)
        static::assertSame([0, 1], array_keys($result['a']));
    }

    public function testSortByPriorityOrdersDescending(): void
    {
        $low = $this->createRule('low', 10);
        $high = $this->createRule('high', 100);

        $collection = new RuleCollection([$low, $high]);
        $collection->sortByPriority();

        static::assertSame(['high', 'low'], array_keys($collection->getElements()));
    }

    public function testEqualsComparesCountAndIds(): void
    {
        $a = $this->createRule('a', 1);
        $b = $this->createRule('b', 1);

        $collection = new RuleCollection([$a, $b]);

        static::assertTrue($collection->equals(new RuleCollection([$a, $b])));
        static::assertFalse($collection->equals(new RuleCollection([$a])));
        static::assertFalse($collection->equals(new RuleCollection([$a, $this->createRule('c', 1)])));
    }

    public function testFilterForContextExcludesFlowConditionRules(): void
    {
        $plain = $this->createRule('plain', 1);
        $flowCondition = $this->createRule('flow-condition', 1);
        $flowCondition->setAreas([RuleAreas::FLOW_CONDITION_AREA]);

        $filtered = (new RuleCollection([$plain, $flowCondition]))->filterForContext();

        static::assertSame(['plain'], array_keys($filtered->getElements()));
    }

    public function testFilterForFlowKeepsOnlyFlowAreaRules(): void
    {
        $plain = $this->createRule('plain', 1);
        $flow = $this->createRule('flow', 1);
        $flow->setAreas([RuleAreas::FLOW_AREA]);

        $filtered = (new RuleCollection([$plain, $flow]))->filterForFlow();

        static::assertSame(['flow'], array_keys($filtered->getElements()));
    }

    public function testFilterMatchingRulesEvaluatesThePayload(): void
    {
        $matching = $this->createRule('matching', 1);
        $matching->setPayload(new AndRule([]));

        $withoutPayload = $this->createRule('no-payload', 1);

        $cart = new Cart('token');
        $context = static::createStub(SalesChannelContext::class);

        $filtered = (new RuleCollection([$matching, $withoutPayload]))->filterMatchingRules($cart, $context);

        static::assertSame(['matching'], array_keys($filtered->getElements()));
    }

    private function createRule(string $id, int $priority): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->setId($id);
        $rule->setPriority($priority);

        return $rule;
    }
}
