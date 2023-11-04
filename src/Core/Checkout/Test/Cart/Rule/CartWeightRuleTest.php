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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class CartWeightRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private CartWeightRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CartWeightRule();
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $weight,
        float $lineItemWeight1,
        float $lineItemWeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false
    ): void {
        $this->rule->assign(['weight' => $weight, 'operator' => $operator]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy($lineItemWeight1, $lineItemWeight2, $lineItem1WithoutDeliveryInfo, $lineItem2WithoutDeliveryInfo),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectOnEqualWeightNested(
        string $operator,
        float $weight,
        float $lineItemWeight1,
        float $lineItemWeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false
    ): void {
        $this->rule->assign(['weight' => $weight, 'operator' => $operator]);
        $cart = $this->createCartDummy($lineItemWeight1, $lineItemWeight2, $lineItem1WithoutDeliveryInfo, $lineItem2WithoutDeliveryInfo);
        $childLineItemCollection = $cart->getLineItems();

        $containerLineItem = $this->createContainerLineItem($childLineItemCollection);

        $cart->setLineItems(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same weight' => [Rule::OPERATOR_EQ, 600, 100, 100, true];
        yield 'no match / operator equals / different weight' => [Rule::OPERATOR_EQ, 200, 100, 100, false];
        yield 'match / operator equals / without delivery info of item 1' => [Rule::OPERATOR_EQ, 300, 100, 100, true, true];
        yield 'match / operator equals / without delivery info of item 1 and 2' => [Rule::OPERATOR_EQ, 0, 100, 100, true, true, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same weight' => [Rule::OPERATOR_NEQ, 600, 100, 100, false];
        yield 'match / operator not equals / different weight' => [Rule::OPERATOR_NEQ, 200, 100, 100, true];
        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 600, 100, 100, true, true];
        yield 'no match / operator not equals / without delivery info of item 1' => [Rule::OPERATOR_NEQ, 300, 100, 100, false, true];
        yield 'no match / operator not equals / without delivery info of item 1 and 2' => [Rule::OPERATOR_NEQ, 0, 100, 100, false, true, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower weight' => [Rule::OPERATOR_GT, 700, 100, 100, false];
        yield 'no match / operator greater than / same weight' => [Rule::OPERATOR_GT, 600, 100, 100, false];
        yield 'match / operator greater than / higher weight' => [Rule::OPERATOR_GT, 200, 100, 100, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower weight' => [Rule::OPERATOR_GTE, 700, 100, 100, false];
        yield 'match / operator greater than equals / same weight' => [Rule::OPERATOR_GTE, 600, 100, 100, true];
        yield 'match / operator greater than equals / higher weight' => [Rule::OPERATOR_GTE, 200, 100, 100, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower weight' => [Rule::OPERATOR_LT, 700, 100, 100, true];
        yield 'no match / operator lower  than / same weight' => [Rule::OPERATOR_LT, 600, 100, 100, false];
        yield 'no match / operator lower than / higher weight' => [Rule::OPERATOR_LT, 200, 100, 100, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower weight' => [Rule::OPERATOR_LTE, 700, 100, 100, true];
        yield 'match / operator lower than equals / same weight' => [Rule::OPERATOR_LTE, 600, 100, 100, true];
        yield 'no match / operator lower than equals / higher weight' => [Rule::OPERATOR_LTE, 200, 100, 100, false];
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

    private function createCartDummy(?float $weight1, ?float $weight2, bool $lineItem1WithoutDeliveryInfo = false, bool $lineItem2WithoutDeliveryInfo = false): Cart
    {
        $lineItem1 = $this->createLineItemWithDeliveryInfo(false, 3, $weight1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithDeliveryInfo(false, 3, $weight2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        return $this->createCart($lineItemCollection);
    }
}
