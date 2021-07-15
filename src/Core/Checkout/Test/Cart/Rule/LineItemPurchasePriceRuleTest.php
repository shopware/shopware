<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemPurchasePriceRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemPurchasePriceRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPurchasePriceRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemPurchasePrice', $this->rule->getName());
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
    public function testIfMatchesCorrectWithLineItemPurchasePriceGross(
        string $operator,
        float $amount,
        float $lineItemPurchasePriceGross,
        bool $expected
    ): void {
        $this->rule->assign([
            'isNet' => false,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithPurchasePrice(0, $lineItemPurchasePriceGross),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItemPurchasePriceNet(
        string $operator,
        float $amount,
        float $lineItemPurchasePriceNet,
        bool $expected
    ): void {
        $this->rule->assign([
            'isNet' => true,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithPurchasePrice($lineItemPurchasePriceNet),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, true],
            'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same price' => [Rule::OPERATOR_NEQ, 100, 100, false],
            'match / operator not equals / different price' => [Rule::OPERATOR_NEQ, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, false],
            'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, false],
            'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, false],
            'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, true],
            'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, true],
            // OPERATOR_LT
            'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, true],
            'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, false],
            'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, true],
            'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, true],
            'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, false],
            // OPERATOR_EMPTY
            'match / operator empty / no price' => [Rule::OPERATOR_EMPTY, 100, 0, true],
            'no match / operator empty / higher price' => [Rule::OPERATOR_EMPTY, 100, 200, false],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopePurchasePrice(
        string $operator,
        float $amount,
        float $lineItemPurchasePrice1,
        float $lineItemPurchasePrice2,
        bool $expected
    ): void {
        $this->rule->assign([
            'isNet' => true,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithPurchasePrice($lineItemPurchasePrice1),
            $this->createLineItemWithPurchasePrice($lineItemPurchasePrice2),
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
    public function testIfMatchesCorrectWithCartRuleScopePurchasePriceNested(
        string $operator,
        float $amount,
        float $lineItemPurchasePrice1,
        float $lineItemPurchasePrice2,
        bool $expected
    ): void {
        $this->rule->assign([
            'isNet' => true,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithPurchasePrice($lineItemPurchasePrice1),
            $this->createLineItemWithPurchasePrice($lineItemPurchasePrice2),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
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
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    private function createLineItemWithPurchasePrice(
        float $purchasePriceNet = 0,
        float $purchasePriceGross = 0
    ): LineItem {
        return ($this->createLineItem())->setPayloadValue(
            'purchasePrices',
            json_encode(new Price(
                Defaults::CURRENCY,
                $purchasePriceNet,
                $purchasePriceGross,
                false
            ))
        );
    }
}
