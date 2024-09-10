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
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => true,
        ];

        yield 'no match / operator equals / different ratio' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'price' => 100,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'no match / operator equals / without price' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'price' => 0,
            'listPrice' => null,
            'expected' => false,
            'lineItemWithoutPrice' => true,
        ];

        yield 'match / operator equals / negative ratio' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 2,
            'price' => 10,
            'listPrice' => 5,
            'expected' => true,
        ];

        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratio' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'match / operator not equals / different ratio' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'price' => 100,
            'listPrice' => 200,
            'expected' => true,
        ];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.75,
            'price' => 50,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'no match / operator greater than / same ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'match / operator greater than / higher ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.25,
            'price' => 100,
            'listPrice' => 250,
            'expected' => true,
        ];

        yield 'match / operator greater than / negative ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 1.5,
            'price' => 10,
            'listPrice' => 5,
            'expected' => true,
        ];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.75,
            'price' => 50,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'match / operator greater than equals / same ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => true,
        ];

        yield 'match / operator greater than equals / higher ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.25,
            'price' => 100,
            'listPrice' => 250,
            'expected' => true,
        ];

        yield 'match / operator greater than equals / negative ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 2,
            'price' => 10,
            'listPrice' => 5,
            'expected' => true,
        ];

        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.5,
            'price' => 50,
            'listPrice' => 200,
            'expected' => true,
        ];

        yield 'no match / operator lower than / same ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'no match / operator lower than / higher ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => false,
        ];

        yield 'match / operator lower than / negative ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 1.5,
            'price' => 7,
            'listPrice' => 5,
            'expected' => true,
        ];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 250,
            'expected' => true,
        ];

        yield 'match / operator lower than equals / same ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.5,
            'price' => 100,
            'listPrice' => 200,
            'expected' => true,
        ];

        yield 'no match / operator lower than equals / higher ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.25,
            'price' => 100,
            'listPrice' => 220,
            'expected' => false,
        ];

        yield 'match / operator lower than equals/ negative ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 1.5,
            'price' => 7,
            'listPrice' => 5,
            'expected' => true,
        ];

        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => null,
            'price' => 100,
            'listPrice' => null,
            'expected' => true,
        ];

        yield 'no match / operator empty / is not empty' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'price' => 200,
            'listPrice' => 250,
            'expected' => false,
        ];

        yield 'match / operator not equals / without price' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'price' => 0,
            'listPrice' => null,
            'expected' => true,
            'lineItemWithoutPrice' => true,
        ];

        yield 'match / operator empty / without price' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'price' => 0,
            'listPrice' => null,
            'expected' => true,
            'lineItemWithoutPrice' => true,
        ];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $ruleRatio,
        float $priceItem1,
        ?float $listPriceItem1,
        float $priceItem2,
        ?float $listPriceItem2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false,
        ?float $containerLineItemPrice = null,
        ?float $containerLineItemListPrice = null
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem1 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($priceItem1, $listPriceItem1);
        $lineItem2 = $lineItem2WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($priceItem2, $listPriceItem2);

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
        ?float $ruleRatio,
        float $priceItem1,
        ?float $listPriceItem1,
        float $priceItem2,
        ?float $listPriceItem2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false,
        ?float $containerLineItemPrice = null,
        ?float $containerLineItemListPrice = null
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem1 = $lineItem1WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($priceItem1, $listPriceItem1);
        $lineItem2 = $lineItem2WithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($priceItem2, $listPriceItem2);

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
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.5,
            'priceItem1' => 100,
            'listPriceItem1' => 200,
            'priceItem2' => 50,
            'listPriceItem2' => 500,
            'expected' => true,
        ];

        yield 'no match / operator equals / different ratio' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 300,
            'priceItem2' => 50,
            'listPriceItem2' => 400,
            'expected' => false,
        ];

        yield 'no match / operator equals / item 1 without price' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 200,
            'listPriceItem2' => 100,
            'expected' => false,
            'lineItem1WithoutPrice' => true,
        ];

        yield 'no match / operator equals / item 2 without price' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 300,
            'priceItem2' => 0,
            'listPriceItem2' => null,
            'expected' => false,
            'lineItem2WithoutPrice' => true,
        ];

        yield 'no match / operator equals / item 1 and 2 without price' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 0,
            'listPriceItem2' => null,
            'expected' => false,
            'lineItem1WithoutPrice' => true,
            'lineItem2WithoutPrice' => true,
        ];
        yield 'match / operator equals / item 1 with negative ratio' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleRatio' => 1.4,
            'priceItem1' => 70,
            'listPriceItem1' => 50,
            'priceItem2' => 50,
            'listPriceItem2' => 400,
            'expected' => true,
        ];

        // OPERATOR_NEQ
        yield 'no match / operator not equals / same ratios' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.5,
            'priceItem1' => 100,
            'listPriceItem1' => 200,
            'priceItem2' => 50,
            'listPriceItem2' => 100,
            'expected' => false,
            'lineItem1WithoutPrice' => false,
            'lineItem2WithoutPrice' => false,
            'containerLineItemPrice' => 100,
            'containerLineItemListPrice' => 200,
        ];

        yield 'match / operator not equals / different ratios' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 50,
            'listPriceItem1' => 200,
            'priceItem2' => 100,
            'listPriceItem2' => 250,
            'expected' => true,
        ];

        yield 'match / operator not equals / different ratios 2' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 300,
            'priceItem2' => 200,
            'listPriceItem2' => 100,
            'expected' => true,
        ];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.75,
            'priceItem1' => 50,
            'listPriceItem1' => 70,
            'priceItem2' => 50,
            'listPriceItem2' => 200,
            'expected' => false,
        ];

        yield 'no match / operator greater than / same ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.5,
            'priceItem1' => 50,
            'listPriceItem1' => 100,
            'priceItem2' => 50,
            'listPriceItem2' => 100,
            'expected' => false,
        ];

        yield 'match / operator greater than / higher ratio' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleRatio' => 0.25,
            'priceItem1' => 50,
            'listPriceItem1' => 100,
            'priceItem2' => 25,
            'listPriceItem2' => 200,
            'expected' => true,
        ];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.9,
            'priceItem1' => 100,
            'listPriceItem1' => 125,
            'priceItem2' => 80,
            'listPriceItem2' => 150,
            'expected' => false,
        ];

        yield 'match / operator greater than equals / same ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.5,
            'priceItem1' => 50,
            'listPriceItem1' => 100,
            'priceItem2' => 50,
            'listPriceItem2' => 100,
            'expected' => true,
        ];

        yield 'match / operator greater than equals / higher ratio' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleRatio' => 0.25,
            'priceItem1' => 50,
            'listPriceItem1' => 250,
            'priceItem2' => 75,
            'listPriceItem2' => 300,
            'expected' => true,
        ];

        // OPERATOR_LT
        yield 'match / operator lower than / lower ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.75,
            'priceItem1' => 50,
            'listPriceItem1' => 200,
            'priceItem2' => 100,
            'listPriceItem2' => 200,
            'expected' => true,
        ];

        yield 'no match / operator lower than / same ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.25,
            'priceItem1' => 50,
            'listPriceItem1' => 200,
            'priceItem2' => 50,
            'listPriceItem2' => 200,
            'expected' => false,
        ];

        yield 'no match / operator lower than / higher ratio' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleRatio' => 0.25,
            'priceItem1' => 50,
            'listPriceItem1' => 200,
            'priceItem2' => 50,
            'listPriceItem2' => 200,
            'expected' => false,
        ];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.75,
            'priceItem1' => 50,
            'listPriceItem1' => 120,
            'priceItem2' => 100,
            'listPriceItem2' => 200,
            'expected' => true,
        ];

        yield 'match / operator lower than equals / same ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.5,
            'priceItem1' => 50,
            'listPriceItem1' => 200,
            'priceItem2' => 100,
            'listPriceItem2' => 200,
            'expected' => true,
        ];

        yield 'no match / operator lower than equals / higher ratio' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleRatio' => 0.25,
            'priceItem1' => 100,
            'listPriceItem1' => 200,
            'priceItem2' => 100,
            'listPriceItem2' => 300,
            'expected' => false,
        ];

        // OPERATOR_EMPTY
        yield 'match / operator empty / is empty' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => null,
            'priceItem1' => 100,
            'listPriceItem1' => null,
            'priceItem2' => 100,
            'listPriceItem2' => null,
            'expected' => true,
        ];

        yield 'no match / operator empty / is not empty' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 200,
            'priceItem2' => 250,
            'listPriceItem2' => 300,
            'expected' => false,
            'lineItem1WithoutPrice' => false,
            'lineItem2WithoutPrice' => false,
            'containerLineItemPrice' => 100,
            'containerLineItemListPrice' => 200,
        ];

        yield 'match / operator not equals / item 1 and 2 without price' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 0,
            'listPriceItem2' => null,
            'expected' => true,
            'lineItem1WithoutPrice' => true,
            'lineItem2WithoutPrice' => true,
        ];

        yield 'match / operator not equals / item 1 without price' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 100,
            'listPriceItem2' => 100,
            'expected' => true,
            'lineItem1WithoutPrice' => true,
        ];

        yield 'match / operator not equals / item 2 without price' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 100,
            'priceItem2' => 100,
            'listPriceItem2' => 100,
            'expected' => true,
            'lineItem2WithoutPrice' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without price' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 0,
            'listPriceItem2' => null,
            'expected' => true,
            'lineItem1WithoutPrice' => true,
            'lineItem2WithoutPrice' => true,
        ];

        yield 'match / operator empty / item 1 without price' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'priceItem1' => 0,
            'listPriceItem1' => null,
            'priceItem2' => 100,
            'listPriceItem2' => 100,
            'expected' => true,
            'lineItem1WithoutPrice' => true,
        ];

        yield 'match / operator empty / item 2 without price' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleRatio' => 0.75,
            'priceItem1' => 100,
            'listPriceItem1' => 100,
            'priceItem2' => 0,
            'listPriceItem2' => null,
            'expected' => true,
            'lineItem2WithoutPrice' => true,
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
