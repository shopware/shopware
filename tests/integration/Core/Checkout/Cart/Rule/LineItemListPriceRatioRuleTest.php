<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRatioRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('services-settings')]
#[Group('rules')]
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

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        ?float $ruleRatio,
        float $price,
        ?float $listPrice,
        bool $expected,
        bool $lineItemWithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem = $lineItemWithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price, $listPrice);

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|float|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => true,
        ];

        yield 'no match / operator equals / different ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'RuleRatio' => 0.75,
            'Price' => 100,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'no match / operator equals / without price' => [
            'Operator' => Rule::OPERATOR_EQ,
            'RuleRatio' => 0.75,
            'Price' => 0,
            'List price' => null,
            'Expected' => false,
            'Line item without price' => true,
        ];

        yield 'match / operator equals / negative ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'RuleRatio' => 2,
            'Price' => 10,
            'List price' => 5,
            'Expected' => true,
        ];

        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratio' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'match / operator not equals / different ratio' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'RuleRatio' => 0.75,
            'Price' => 100,
            'List price' => 200,
            'Expected' => true,
        ];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'RuleRatio' => 0.75,
            'Price' => 50,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'no match / operator greater than / same ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'match / operator greater than / higher ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'RuleRatio' => 0.25,
            'Price' => 100,
            'List price' => 250,
            'Expected' => true,
        ];

        yield 'match / operator greater than / negative ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'RuleRatio' => 1.5,
            'Price' => 10,
            'List price' => 5,
            'Expected' => true,
        ];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'RuleRatio' => 0.75,
            'Price' => 50,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'match / operator greater than equals / same ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => true,
        ];

        yield 'match / operator greater than equals / higher ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'RuleRatio' => 0.25,
            'Price' => 100,
            'List price' => 250,
            'Expected' => true,
        ];

        yield 'match / operator greater than equals / negative ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'RuleRatio' => 2,
            'Price' => 10,
            'List price' => 5,
            'Expected' => true,
        ];

        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'RuleRatio' => 0.5,
            'Price' => 50,
            'List price' => 200,
            'Expected' => true,
        ];

        yield 'no match / operator lower than / same ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'no match / operator lower than / higher ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => false,
        ];

        yield 'match / operator lower than / negative ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'RuleRatio' => 1.5,
            'Price' => 7,
            'List price' => 5,
            'Expected' => true,
        ];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 250,
            'Expected' => true,
        ];

        yield 'match / operator lower than equals / same ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'RuleRatio' => 0.5,
            'Price' => 100,
            'List price' => 200,
            'Expected' => true,
        ];

        yield 'no match / operator lower than equals / higher ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'RuleRatio' => 0.25,
            'Price' => 100,
            'List price' => 220,
            'Expected' => false,
        ];

        yield 'match / operator lower than equals/ negative ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'RuleRatio' => 1.5,
            'Price' => 7,
            'List price' => 5,
            'Expected' => true,
        ];

        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'RuleRatio' => null,
            'Price' => 100,
            'List price' => null,
            'Expected' => true,
        ];

        yield 'no match / operator empty / is not empty' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'RuleRatio' => 0.75,
            'Price' => 200,
            'List price' => 250,
            'Expected' => false,
        ];

        yield 'match / operator not equals / without price' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'RuleRatio' => 0.75,
            'Price' => 0,
            'List price' => null,
            'Expected' => true,
            'Line item without price' => true,
        ];

        yield 'match / operator empty / without price' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'RuleRatio' => 0.75,
            'Price' => 0,
            'List price' => null,
            'Expected' => true,
            'Line item without price' => true,
        ];
    }

    #[DataProvider('getCartRuleScopeTestData')]
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

        $lineItem1 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price1, $listPrice1);
        $lineItem2 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price2, $listPrice2);

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

    #[DataProvider('getCartRuleScopeTestData')]
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

        $lineItem1 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price1, $listPrice1);
        $lineItem2 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price2, $listPrice2);

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
     * @return \Traversable<string, array<string|float|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 0.5,
            'Price of item 1' => 100,
            'List price of item 1' => 200,
            'Price of item 2' => 50,
            'List price of item 2' => 500,
            'Expected' => true,
        ];

        yield 'no match / operator equals / different ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 300,
            'Price of item 2' => 50,
            'List price of item 2' => 400,
            'Expected' => false,
        ];

        yield 'no match / operator equals / item 1 without price' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 200,
            'List price of item 2' => 100,
            'Expected' => false,
            'Line item 1 without price' => true,
        ];

        yield 'no match / operator equals / item 2 without price' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 300,
            'Price of item 2' => 0,
            'List price of item 2' => null,
            'Expected' => false,
            'Line item 2 without price' => true,
        ];

        yield 'no match / operator equals / item 1 and 2 without price' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 0,
            'List price of item 2' => null,
            'Expected' => false,
            'Line item 1 without price' => true,
            'Line item 2 without price' => true,
        ];
        yield 'match / operator equals / item 1 with negative ratio' => [
            'Operator' => Rule::OPERATOR_EQ,
            'Rule ratio' => 1.4,
            'Price of item 1' => 70,
            'List price of item 1' => 50,
            'Price of item 2' => 50,
            'List price of item 2' => 400,
            'Expected' => true,
        ];


        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratios' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.5,
            'Price of item 1' => 100,
            'List price of item 1' => 200,
            'Price of item 2' => 50,
            'List price of item 2' => 100,
            'Expected' => false,
            'Line item 1 without price' => false,
            'Line item 2 without price' => false,
            'Container line item price' => 100,
            'Container line item list price' => 200,
        ];

        yield 'match / operator not equals / different ratios' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 50,
            'List price of item 1' => 200,
            'Price of item 2' => 100,
            'List price of item 2' => 250,
            'Expected' => true,
        ];

        yield 'match / operator not equals / different ratios 2' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 300,
            'Price of item 2' => 200,
            'List price of item 2' => 100,
            'Expected' => true,
        ];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'Rule ratio' => 0.75,
            'Price of item 1' => 50,
            'List price of item 1' => 70,
            'Price of item 2' => 50,
            'List price of item 2' => 200,
            'Expected' => false,
        ];

        yield 'no match / operator greater than / same ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'Rule ratio' => 0.5,
            'Price of item 1' => 50,
            'List price of item 1' => 100,
            'Price of item 2' => 50,
            'List price of item 2' => 100,
            'Expected' => false,
        ];

        yield 'match / operator greater than / higher ratio' => [
            'Operator' => Rule::OPERATOR_GT,
            'Rule ratio' => 0.25,
            'Price of item 1' => 50,
            'List price of item 1' => 100,
            'Price of item 2' => 25,
            'List price of item 2' => 200,
            'Expected' => true,
        ];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'Rule ratio' => 0.9,
            'Price of item 1' => 100,
            'List price of item 1' => 125,
            'Price of item 2' => 80,
            'List price of item 2' => 150,
            'Expected' => false,
        ];

        yield 'match / operator greater than equals / same ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'Rule ratio' => 0.5,
            'Price of item 1' => 50,
            'List price of item 1' => 100,
            'Price of item 2' => 50,
            'List price of item 2' => 100,
            'Expected' => true,
        ];

        yield 'match / operator greater than equals / higher ratio' => [
            'Operator' => Rule::OPERATOR_GTE,
            'Rule ratio' => 0.25,
            'Price of item 1' => 50,
            'List price of item 1' => 250,
            'Price of item 2' => 75,
            'List price of item 2' => 300,
            'Expected' => true,
        ];

        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'Rule ratio' => 0.75,
            'Price of item 1' => 50,
            'List price of item 1' => 200,
            'Price of item 2' => 100,
            'List price of item 2' => 200,
            'Expected' => true,
        ];

        yield 'no match / operator lower than / same ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'Rule ratio' => 0.25,
            'Price of item 1' => 50,
            'List price of item 1' => 200,
            'Price of item 2' => 50,
            'List price of item 2' => 200,
            'Expected' => false,
        ];

        yield 'no match / operator lower than / higher ratio' => [
            'Operator' => Rule::OPERATOR_LT,
            'Rule ratio' => 0.25,
            'Price of item 1' => 50,
            'List price of item 1' => 200,
            'Price of item 2' => 50,
            'List price of item 2' => 200,
            'Expected' => false,
        ];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'Rule ratio' => 0.75,
            'Price of item 1' => 50,
            'List price of item 1' => 120,
            'Price of item 2' => 100,
            'List price of item 2' => 200,
            'Expected' => true,
        ];

        yield 'match / operator lower than equals / same ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'Rule ratio' => 0.5,
            'Price of item 1' => 50,
            'List price of item 1' => 200,
            'Price of item 2' => 100,
            'List price of item 2' => 200,
            'Expected' => true,
        ];

        yield 'no match / operator lower than equals / higher ratio' => [
            'Operator' => Rule::OPERATOR_LTE,
            'Rule ratio' => 0.25,
            'Price of item 1' => 100,
            'List price of item 1' => 200,
            'Price of item 2' => 100,
            'List price of item 2' => 300,
            'Expected' => false,
        ];

        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'Rule ratio' => null,
            'Price of item 1' => 100,
            'List price of item 1' => null,
            'Price of item 2' => 100,
            'List price of item 2' => null,
            'Expected' => true,
        ];

        yield 'no match / operator empty / is not empty' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 200,
            'Price of item 2' => 250,
            'List price of item 2' => 300,
            'Expected' => false,
            'Line item 1 without price' => false,
            'Line item 2 without price' => false,
            'Container line item price' => 100,
            'Container line item list price' => 200,
        ];

        yield 'match / operator not equals / item 1 and 2 without price' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 0,
            'List price of item 2' => null,
            'Expected' => true,
            'Line item 1 without price' => true,
            'Line item 2 without price' => true,
        ];

        yield 'match / operator not equals / item 1 without price' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 100,
            'List price of item 2' => 100,
            'Expected' => true,
            'Line item 1 without price' => true,
        ];

        yield 'match / operator not equals / item 2 without price' => [
            'Operator' => Rule::OPERATOR_NEQ,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 100,
            'Price of item 2' => 100,
            'List price of item 2' => 100,
            'Expected' => true,
            'Line item 2 without price' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without price' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 0,
            'List price of item 2' => null,
            'Expected' => true,
            'Line item 1 without price' => true,
            'Line item 2 without price' => true,
        ];

        yield 'match / operator empty / item 1 without price' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'Rule ratio' => 0.75,
            'Price of item 1' => 0,
            'List price of item 1' => null,
            'Price of item 2' => 100,
            'List price of item 2' => 100,
            'Expected' => true,
            'Line item 1 without price' => true,
        ];

        yield 'match / operator empty / item 2 without price' => [
            'Operator' => Rule::OPERATOR_EMPTY,
            'Rule ratio' => 0.75,
            'Price of item 1' => 100,
            'List price of item 1' => 100,
            'Price of item 2' => 0,
            'List price of item 2' => null,
            'Expected' => true,
            'Line item 2 without price' => true,
        ];
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
