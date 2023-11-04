<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRatioRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemListPriceRatioRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private LineItemListPriceRatioRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemListPriceRatioRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemListPriceRatio', $this->rule->getName());
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
        ?float $percentage,
        float $price,
        ?float $listPrice,
        bool $expected,
        bool $lineItemWithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $percentage,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithListPrice($price, $listPrice);
        if ($lineItemWithoutPrice) {
            $lineItem = $this->createLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same ratio' => [Rule::OPERATOR_EQ, 50, 100, 200, true];
        yield 'no match / operator equals / different ratio' => [Rule::OPERATOR_EQ, 200, 100, 200, false];
        yield 'no match / operator equals / without price' => [Rule::OPERATOR_EQ, 200, 100, 200, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratio' => [Rule::OPERATOR_NEQ, 50, 100, 200, false];
        yield 'match / operator not equals / different ratio' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [Rule::OPERATOR_GT, 100, 50, 200, false];
        yield 'no match / operator greater than / same ratio' => [Rule::OPERATOR_GT, 50, 100, 200, false];
        yield 'match / operator greater than / higher ratio' => [Rule::OPERATOR_GT, 50, 100, 250, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [Rule::OPERATOR_GTE, 100, 50, 200, false];
        yield 'match / operator greater than equals / same ratio' => [Rule::OPERATOR_GTE, 50, 100, 200, true];
        yield 'match / operator greater than equals / higher ratio' => [Rule::OPERATOR_GTE, 50, 100, 250, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [Rule::OPERATOR_LT, 50, 100, 125, true];
        yield 'no match / operator lower  than / same ratio' => [Rule::OPERATOR_LT, 50, 100, 200, false];
        yield 'no match / operator lower than / higher ratio' => [Rule::OPERATOR_LT, 50, 100, 250, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [Rule::OPERATOR_LTE, 50, 100, 150, true];
        yield 'match / operator lower than equals / same ratio' => [Rule::OPERATOR_LTE, 50, 100, 200, true];
        yield 'no match / operator lower than equals / higher ratio' => [Rule::OPERATOR_LTE, 50, 100, 220, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [Rule::OPERATOR_EMPTY, null, 100, null, true];
        yield 'no match / operator empty / is not empty' => [Rule::OPERATOR_EMPTY, 100, 200, 250, false];

        yield 'match / operator not equals / without price' => [Rule::OPERATOR_NEQ, 200, 100, 300, true, true];
        yield 'match / operator empty / without price' => [Rule::OPERATOR_EMPTY, 100, 200, 300, true, true];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $amount,
        float $price1,
        ?float $listPrice1,
        float $price2,
        ?float $listPrice2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithListPrice($price1, $listPrice1);
        if ($lineItem1WithoutPrice) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithListPrice($price2, $listPrice2);
        if ($lineItem2WithoutPrice) {
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
        ?float $amount,
        float $price1,
        ?float $listPrice1,
        float $price2,
        ?float $listPrice2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false,
        ?float $containerLineItemPrice = null,
        ?float $containerLineItemListPrice = null
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithListPrice($price1, $listPrice1);
        if ($lineItem1WithoutPrice) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithListPrice($price2, $listPrice2);
        if ($lineItem2WithoutPrice) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = $this->createLineItem();
        if ($containerLineItemPrice !== null && $containerLineItemListPrice !== null) {
            $containerLineItem = $this->createLineItemWithListPrice($containerLineItemPrice, $containerLineItemListPrice);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same ratio' => [Rule::OPERATOR_EQ, 50, 100, 200, 50, 500, true];
        yield 'no match / operator equals / different ratio' => [Rule::OPERATOR_EQ, 50, 100, 300, 50, 400, false];
        yield 'no match / operator equals / item 1 without price' => [Rule::OPERATOR_EQ, 200, 100, 300, 200, 100, false, true];
        yield 'no match / operator equals / item 2 without price' => [Rule::OPERATOR_EQ, 200, 100, 300, 200, 100, false, false, true];
        yield 'no match / operator equals / item 1 and 2 without price' => [Rule::OPERATOR_EQ, 200, 100, 300, 200, 100, false, true, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratios' => [Rule::OPERATOR_NEQ, 50, 100, 200, 50, 100, false, false, false, 100, 200];
        yield 'match / operator not equals / different ratios' => [Rule::OPERATOR_NEQ, 50, 50, 200, 100, 250, true];
        yield 'match / operator not equals / different ratios 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [Rule::OPERATOR_GT, 100, 50, 70, 50, 200, false];
        yield 'no match / operator greater than / same ratio' => [Rule::OPERATOR_GT, 50, 50, 100, 50, 100, false];
        yield 'match / operator greater than / higher ratio' => [Rule::OPERATOR_GT, 50, 50, 300, 25, 500, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [Rule::OPERATOR_GTE, 100, 100, 125, 80, 150, false];
        yield 'match / operator greater than equals / same ratio' => [Rule::OPERATOR_GTE, 50, 50, 100, 50, 100, true];
        yield 'match / operator greater than equals / higher ratio' => [Rule::OPERATOR_GTE, 50, 50, 250, 75, 300, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [Rule::OPERATOR_LT, 100, 50, 200, 100, 200, true];
        yield 'no match / operator lower  than / same ratio' => [Rule::OPERATOR_LT, 50, 50, 200, 50, 200, false];
        yield 'no match / operator lower than / higher ratio' => [Rule::OPERATOR_LT, 20, 50, 200, 50, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [Rule::OPERATOR_LTE, 100, 50, 120, 100, 200, true];
        yield 'match / operator lower than equals / same ratio' => [Rule::OPERATOR_LTE, 50, 50, 200, 100, 200, true];
        yield 'no match / operator lower than equals / higher ratio' => [Rule::OPERATOR_LTE, 25, 100, 200, 100, 300, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [Rule::OPERATOR_EMPTY, null, 100, null, 100, null, true];
        yield 'no match / operator empty / is not empty' => [Rule::OPERATOR_EMPTY, 100, 100, 200, 250, 300, false, false, false, 100, 200];

        yield 'match / operator not equals / item 1 and 2 without price' => [Rule::OPERATOR_NEQ, 200, 100, 300, 200, 100, true, true, true];
        yield 'match / operator not equals / item 1 without price' => [Rule::OPERATOR_NEQ, 100, 100, 100, 100, 100, true, true];
        yield 'match / operator not equals / item 2 without price' => [Rule::OPERATOR_NEQ, 100, 100, 100, 100, 100, true, false, true];

        yield 'match / operator empty / item 1 and 2 without price' => [Rule::OPERATOR_EMPTY, 200, 100, 300, 200, 100, true, true, true];
        yield 'match / operator empty / item 1 without price' => [Rule::OPERATOR_EMPTY, 100, 100, 100, 100, 100, true, true];
        yield 'match / operator empty / item 2 without price' => [Rule::OPERATOR_EMPTY, 100, 100, 100, 100, 100, true, false, true];
    }

    /**
     * @throws CartException
     */
    public function testMatchWithEmptyListPrice(): void
    {
        $price = 100;

        $this->rule->assign(['amount' => $price, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithPrice(LineItem::PRODUCT_LINE_ITEM_TYPE, $price),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    private function createLineItemWithListPrice(float $price, ?float $listPriceAmount): LineItem
    {
        $listPrice = $listPriceAmount === null ? null : ListPrice::createFromUnitPrice($price, $listPriceAmount);

        return $this->createLineItemWithPrice(LineItem::PRODUCT_LINE_ITEM_TYPE, $price, $listPrice);
    }
}
