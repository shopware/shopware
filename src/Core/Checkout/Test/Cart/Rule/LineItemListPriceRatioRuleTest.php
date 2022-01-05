<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRatioRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
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
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $percentage,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithListPrice($price, $listPrice),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same ratio' => [Rule::OPERATOR_EQ, 50, 100, 200, true],
            'no match / operator equals / different ratio' => [Rule::OPERATOR_EQ, 200, 100, 200, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same ratio' => [Rule::OPERATOR_NEQ, 50, 100, 200, false],
            'match / operator not equals / different ratio' => [Rule::OPERATOR_NEQ, 200, 100, 200, true],
            // OPERATOR_GT
            'no match / operator greater than / lower ratio' => [Rule::OPERATOR_GT, 100, 50, 200, false],
            'no match / operator greater than / same ratio' => [Rule::OPERATOR_GT, 50, 100, 200, false],
            'match / operator greater than / higher ratio' => [Rule::OPERATOR_GT, 50, 100, 250, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower ratio' => [Rule::OPERATOR_GTE, 100, 50, 200, false],
            'match / operator greater than equals / same ratio' => [Rule::OPERATOR_GTE, 50, 100, 200, true],
            'match / operator greater than equals / higher ratio' => [Rule::OPERATOR_GTE, 50, 100, 250, true],
            // OPERATOR_LT
            'match / operator lower than / lower ratio' => [Rule::OPERATOR_LT, 50, 100, 125, true],
            'no match / operator lower  than / same ratio' => [Rule::OPERATOR_LT, 50, 100, 200, false],
            'no match / operator lower than / higher ratio' => [Rule::OPERATOR_LT, 50, 100, 250, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower ratio' => [Rule::OPERATOR_LTE, 50, 100, 150, true],
            'match / operator lower than equals / same ratio' => [Rule::OPERATOR_LTE, 50, 100, 200, true],
            'no match / operator lower than equals / higher ratio' => [Rule::OPERATOR_LTE, 50, 100, 220, false],
            // OPERATOR_EMPTY
            'match / operator empty / is empty' => [Rule::OPERATOR_EMPTY, null, 100, null, true],
            'no match / operator empty / is not empty' => [Rule::OPERATOR_EMPTY, 100, 200, 250, false],
        ];
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
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithListPrice($price1, $listPrice1),
            $this->createLineItemWithListPrice($price2, $listPrice2),
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
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithListPrice($price1, $listPrice1),
            $this->createLineItemWithListPrice($price2, $listPrice2),
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
            'match / operator equals / same ratio' => [Rule::OPERATOR_EQ, 50, 100, 200, 50, 500, true],
            'no match / operator equals / different ratio' => [Rule::OPERATOR_EQ, 50, 100, 300, 50, 400, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same ratios' => [Rule::OPERATOR_NEQ, 50, 100, 200, 50, 100, false],
            'match / operator not equals / different ratios' => [Rule::OPERATOR_NEQ, 50, 50, 200, 100, 250, true],
            'match / operator not equals / different ratios 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower ratio' => [Rule::OPERATOR_GT, 100, 50, 70, 50, 200, false],
            'no match / operator greater than / same ratio' => [Rule::OPERATOR_GT, 50, 50, 100, 50, 100, false],
            'match / operator greater than / higher ratio' => [Rule::OPERATOR_GT, 50, 50, 300, 25, 500, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower ratio' => [Rule::OPERATOR_GTE, 100, 100, 125, 80, 150, false],
            'match / operator greater than equals / same ratio' => [Rule::OPERATOR_GTE, 50, 50, 100, 50, 100, true],
            'match / operator greater than equals / higher ratio' => [Rule::OPERATOR_GTE, 50, 50, 250, 75, 300, true],
            // OPERATOR_LT
            'match / operator lower than / lower ratio' => [Rule::OPERATOR_LT, 100, 50, 200, 100, 200, true],
            'no match / operator lower  than / same ratio' => [Rule::OPERATOR_LT, 50, 50, 200, 50, 200, false],
            'no match / operator lower than / higher ratio' => [Rule::OPERATOR_LT, 20, 50, 200, 50, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower ratio' => [Rule::OPERATOR_LTE, 100, 50, 120, 100, 200, true],
            'match / operator lower than equals / same ratio' => [Rule::OPERATOR_LTE, 50, 50, 200, 100, 200, true],
            'no match / operator lower than equals / higher ratio' => [Rule::OPERATOR_LTE, 25, 100, 200, 100, 300, false],
            // OPERATOR_EMPTY
            'match / operator empty / is empty' => [Rule::OPERATOR_EMPTY, null, 100, null, 100, null, true],
            'no match / operator empty / is not empty' => [Rule::OPERATOR_EMPTY, 100, 100, 200, 250, 300, false],
        ];
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testMatchWithEmptyCalculatedPrice(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @throws InvalidQuantityException
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
