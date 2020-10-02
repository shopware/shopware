<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemPurchasePriceRuleTest extends TestCase
{
    /**
     * @var LineItemPurchasePriceRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPurchasePriceRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemPurchasePrice', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @deprecated tag:v6.4.0 - purchasePrice will be removed in 6.4.0 use purchasePrices
     *
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(string $operator, float $amount, float $lineItemAmount, bool $expected): void
    {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($lineItemAmount),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItemPurchasePriceGross(string $operator, float $amount, float $lineItemPurchasePriceGross, bool $expected): void
    {
        $this->rule->assign([
            'isNet' => false,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithPurchasePrice(0, $lineItemPurchasePriceGross),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItemPurchasePriceNet(string $operator, float $amount, float $lineItemPurchasePriceNet, bool $expected): void
    {
        $this->rule->assign([
            'isNet' => true,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithPurchasePrice($lineItemPurchasePriceNet, 0),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same price' => [LineItemPurchasePriceRule::OPERATOR_EQ, 100, 100, true],
            'no match / operator equals / different price' => [LineItemPurchasePriceRule::OPERATOR_EQ, 200, 100, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same price' => [LineItemPurchasePriceRule::OPERATOR_NEQ, 100, 100, false],
            'match / operator not equals / different price' => [LineItemPurchasePriceRule::OPERATOR_NEQ, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower price' => [LineItemPurchasePriceRule::OPERATOR_GT, 100, 50, false],
            'no match / operator greater than / same price' => [LineItemPurchasePriceRule::OPERATOR_GT, 100, 100, false],
            'match / operator greater than / higher price' => [LineItemPurchasePriceRule::OPERATOR_GT, 100, 200, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower price' => [LineItemPurchasePriceRule::OPERATOR_GTE, 100, 50, false],
            'match / operator greater than equals / same price' => [LineItemPurchasePriceRule::OPERATOR_GTE, 100, 100, true],
            'match / operator greater than equals / higher price' => [LineItemPurchasePriceRule::OPERATOR_GTE, 100, 200, true],
            // OPERATOR_LT
            'match / operator lower than / lower price' => [LineItemPurchasePriceRule::OPERATOR_LT, 100, 50, true],
            'no match / operator lower  than / same price' => [LineItemPurchasePriceRule::OPERATOR_LT, 100, 100, false],
            'no match / operator lower than / higher price' => [LineItemPurchasePriceRule::OPERATOR_LT, 100, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower price' => [LineItemPurchasePriceRule::OPERATOR_LTE, 100, 50, true],
            'match / operator lower than equals / same price' => [LineItemPurchasePriceRule::OPERATOR_LTE, 100, 100, true],
            'no match / operator lower than equals / higher price' => [LineItemPurchasePriceRule::OPERATOR_LTE, 100, 200, false],
        ];
    }

    /**
     * @deprecated tag:v6.4.0 - purchasePrice will be removed in 6.4.0 use purchasePrices
     *
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(string $operator, float $amount, float $lineItemAmount1, float $lineItemAmount2, bool $expected): void
    {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($lineItemAmount1));
        $lineItemCollection->add($this->createLineItem($lineItemAmount2));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopePurchasePrice(string $operator, float $amount, float $lineItemPurchasePrice1, float $lineItemPurchasePrice2, bool $expected): void
    {
        $this->rule->assign([
            'isNet' => true,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItemWithPurchasePrice($lineItemPurchasePrice1));
        $lineItemCollection->add($this->createLineItemWithPurchasePrice($lineItemPurchasePrice2));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, 200, true],
            'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, 300, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same prices' => [Rule::OPERATOR_NEQ, 100, 100, 100, false],
            'match / operator not equals / different prices' => [Rule::OPERATOR_NEQ, 200, 100, 200, true],
            'match / operator not equals / different prices 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true],
            // OPERATOR_GT
            'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, 70, false],
            'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, 70, false],
            'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, 70, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, 70, false],
            'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, 70, true],
            'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, 70, true],
            // OPERATOR_LT
            'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, 120, true],
            'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, 120, false],
            'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, 120, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, 120, true],
            'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, 120, true],
            'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, 120, false],
        ];
    }

    public function testMatchWithEmptyPurchasePricePayload(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            new LineItem('dummy-article', 'product', null, 3),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @deprecated tag:v6.4.0 - purchasePrice will be removed in 6.4.0 use purchasePrices
     */
    private function createLineItem(float $purchasePrice): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('purchasePrice', $purchasePrice);
    }

    private function createLineItemWithPurchasePrice(
        float $purchasePriceNet = 0,
        float $purchasePriceGross = 0
    ): LineItem {
        $lineItemWithPurchasePrice = new LineItem(Uuid::randomHex(), 'product', null, 3);
        $lineItemWithPurchasePrice->setPayloadValue(
            'purchasePrices',
            json_encode(new Price(
                Defaults::CURRENCY,
                $purchasePriceNet,
                $purchasePriceGross,
                false
            ))
        );

        return $lineItemWithPurchasePrice;
    }
}
