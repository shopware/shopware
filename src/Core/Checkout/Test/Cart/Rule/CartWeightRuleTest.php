<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class CartWeightRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private CartWeightRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CartWeightRule();
    }

    public function testIfMatchesCorrectOnEqualWeight(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testIfMatchesCorrectOnEqualWeightNested(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => Rule::OPERATOR_EQ]);
        $cart = $this->createCartDummy();
        $childLineItemCollection = $cart->getLineItems();

        $containerLineItem = $this->createContainerLineItem($childLineItemCollection);

        $cart->setLineItems(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testIfMatchesUnequal(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => Rule::OPERATOR_NEQ]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testIfGreaterThanIsCorrect(): void
    {
        $this->rule->assign(['weight' => 300, 'operator' => Rule::OPERATOR_GT]);

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
                    'weight' => 9000.1,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $context);

        $result = $conditionRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertNotNull($result);
        static::assertSame(9000.1, $result->getValue()['weight']);
        static::assertSame(Rule::OPERATOR_EQ, $result->getValue()['operator']);
    }

    private function createCartDummy(): Cart
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithDeliveryInfo(false, 3),
            $this->createLineItemWithDeliveryInfo(true, 3),
        ]);

        return $this->createCart($lineItemCollection);
    }
}
