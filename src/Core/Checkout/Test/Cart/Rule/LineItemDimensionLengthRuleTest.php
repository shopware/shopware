<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 * @group rules
 */
class LineItemDimensionLengthRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionLengthRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionLengthRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionLength', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $amount,
        ?float $lineItemAmount,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithLength($lineItemAmount);
        if ($lineItemWithoutDeliveryInfo) {
            $lineItem = $this->createLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same length' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different length' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 100, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same length' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different length' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower length' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same length' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher length' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower length' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same length' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher length' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower length' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same length' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher length' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower length' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same length' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher length' => [Rule::OPERATOR_LTE, 100, 200, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / null length' => [Rule::OPERATOR_EMPTY, 100, null, true];
        yield 'no match / operator empty / length' => [Rule::OPERATOR_EMPTY, 100, 200, false];

        if (!Feature::isActive('v6.5.0.0')) {
            yield 'no match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, false, true];
            yield 'no match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, 100, 200, false, true];

            return;
        }

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, 100, 200, true, true];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithLength($lineItemAmount1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithLength($lineItemAmount2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemAmount = null
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithLength($lineItemAmount1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithLength($lineItemAmount2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $containerLineItem = $this->createLineItem();
        if ($containerLineItemAmount !== null) {
            $containerLineItem = $this->createLineItemWithLength($containerLineItemAmount);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same length' => [Rule::OPERATOR_EQ, 100, 100, 200, true];
        yield 'no match / operator equals / different length' => [Rule::OPERATOR_EQ, 200, 100, 300, false];
        yield 'no match / operator equals / item 1 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true];
        yield 'no match / operator equals / item 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, false, true];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same length' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, false, false, 100];
        yield 'match / operator not equals / different length' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        yield 'match / operator not equals / different length 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower length' => [Rule::OPERATOR_GT, 100, 50, 70, false];
        yield 'no match / operator greater than / same length' => [Rule::OPERATOR_GT, 100, 100, 70, false];
        yield 'match / operator greater than / higher length' => [Rule::OPERATOR_GT, 100, 200, 70, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower length' => [Rule::OPERATOR_GTE, 100, 50, 70, false];
        yield 'match / operator greater than equals / same length' => [Rule::OPERATOR_GTE, 100, 100, 70, true];
        yield 'match / operator greater than equals / higher length' => [Rule::OPERATOR_GTE, 100, 200, 70, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower length' => [Rule::OPERATOR_LT, 100, 50, 120, true];
        yield 'no match / operator lower  than / same length' => [Rule::OPERATOR_LT, 100, 100, 120, false];
        yield 'no match / operator lower than / higher length' => [Rule::OPERATOR_LT, 100, 200, 120, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower length' => [Rule::OPERATOR_LTE, 100, 50, 120, true];
        yield 'match / operator lower than equals / same length' => [Rule::OPERATOR_LTE, 100, 100, 120, true];
        yield 'no match / operator lower than equals / higher length' => [Rule::OPERATOR_LTE, 100, 200, 120, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / null length 1' => [Rule::OPERATOR_EMPTY, 100, null, 120, true];
        yield 'match / operator empty / null length 2' => [Rule::OPERATOR_EMPTY, 100, 100, null, true];
        yield 'no match / operator empty / length' => [Rule::OPERATOR_EMPTY, 100, 200, 120, false, false, false, 200];

        if (!Feature::isActive('v6.5.0.0')) {
            yield 'no match / operator not equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, 300, false, true, true];
            yield 'no match / operator not equals / item 1 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, true];
            yield 'no match / operator not equals / item 2 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, false, true];

            yield 'no match / operator empty / item 1 and 2 without delivery info' => [Rule::OPERATOR_EMPTY, 200, 100, 300, false, true, true];
            yield 'no match / operator empty / item 1 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, false, true];
            yield 'no match / operator empty / item 2 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, false, false, true];

            return;
        }

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, 300, true, true, true];
        yield 'match / operator not equals / item 1 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, true];
        yield 'match / operator not equals / item 2 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, false, true];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [Rule::OPERATOR_EMPTY, 200, 100, 300, true, true, true];
        yield 'match / operator empty / item 1 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, true];
        yield 'match / operator empty / item 2 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, false, true];
    }

    private function createLineItemWithLength(?float $length): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 50.0, null, null, $length);
    }
}
