<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartWeightRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var CartWeightRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new CartWeightRule();
    }

    public function testIfMatchesCorrectOnEqualWeight(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => CartWeightRule::OPERATOR_EQ]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testIfMatchesUnequal(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => CartWeightRule::OPERATOR_NEQ]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testIfGreaterThanIsCorrect(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => CartWeightRule::OPERATOR_GT]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);

        $this->rule->assign(['weight' => 200]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

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
                'type' => (new CartWeightRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'weight' => (float) 9000,
                    'operator' => CartWeightRule::OPERATOR_EQ,
                ],
            ],
        ], $context);

        /* @var RuleConditionEntity $result */
        $result = $conditionRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertNotNull($result);
        static::assertEquals('9000', $result->getValue()['weight']);
        static::assertEquals(CartWeightRule::OPERATOR_EQ, $result->getValue()['operator']);
    }

    private function createCartDummy(): Cart
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add((new LineItem('dummyWithShippingCost', 'product', null, 3))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                50.0,
                false,
                null,
                (new DeliveryTime())->assign([
                    'max' => 3,
                    'min' => 1,
                    'unit' => 'week',
                    'name' => '1-3 weeks',
                ])
            )
        ));
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    true,
                    null,
                    (new DeliveryTime())->assign([
                        'max' => 3,
                        'min' => 1,
                        'unit' => 'week',
                        'name' => '1-3 weeks',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        return $cart;
    }
}
