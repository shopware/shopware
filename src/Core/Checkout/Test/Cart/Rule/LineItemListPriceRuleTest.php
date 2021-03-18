<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemListPriceRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private LineItemListPriceRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemListPriceRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemListPrice', $this->rule->getName());
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
        float $lineItemAmount,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithListPrice($lineItemAmount),
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
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $amount,
        float $lineItemAmount1,
        float $lineItemAmount2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithListPrice($lineItemAmount1),
            $this->createLineItemWithListPrice($lineItemAmount2),
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
        float $lineItemAmount1,
        float $lineItemAmount2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithListPrice($lineItemAmount1),
            $this->createLineItemWithListPrice($lineItemAmount2),
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

    public function testProductListPrice(): void
    {
        $ids = new TestDataCollection();

        $itemRounding = json_encode(new CashRoundingConfig(2, 0.01, true));
        static::assertNotFalse($itemRounding);
        $totalRounding = json_encode(new CashRoundingConfig(2, 0.01, true));
        static::assertNotFalse($totalRounding);

        $currency = [
            'id' => $ids->create('currency'),
            'name' => 'dollar',
            'factor' => 1.3,
            'symbol' => '$',
            'isoCode' => 'US',
            'decimalPrecision' => 2,
            'shortName' => 'dollar',
            'itemRounding' => json_decode($itemRounding, true),
            'totalRounding' => json_decode($totalRounding, true),
        ];

        $this->getContainer()->get('currency.repository')
            ->create([$currency], Context::createDefaultContext());

        // create product with two different currency prices
        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => $ids->create('get'),
            'stock' => 10,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 15,
                    'net' => 10,
                    'linked' => false,
                    'listPrice' => ['gross' => 20, 'net' => 15, 'linked' => false],
                ],
                [
                    'currencyId' => $ids->get('currency'),
                    'gross' => 5,
                    'net' => 5,
                    'linked' => false,
                    'listPrice' => ['gross' => 15, 'net' => 15, 'linked' => false],
                ],
            ],
            'active' => true,
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', Defaults::SALES_CHANNEL);

        $service = $this->getContainer()->get(CartService::class);

        // create cart and product line item
        $cart = $service->getCart('test', $context);
        $lineItem = $this->getContainer()
            ->get(ProductLineItemFactory::class)
            ->create($ids->get('product'));

        $service->add($cart, $lineItem, $context);
        static::assertTrue($cart->has($ids->get('product')));

        // assert list price is calculated for default price
        $lineItem = $cart->get($ids->get('product'));
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertInstanceOf(CalculatedPrice::class, $lineItem->getPrice());
        static::assertInstanceOf(ListPrice::class, $lineItem->getPrice()->getListPrice());
        $listPrice = $lineItem->getPrice()->getListPrice();
        static::assertSame(20.0, $listPrice->getPrice());

        $rules = [
            new LineItemListPriceRule(Rule::OPERATOR_GTE, 19),
            new LineItemListPriceRule(Rule::OPERATOR_GT, 19),
            new LineItemListPriceRule(Rule::OPERATOR_LTE, 21),
            new LineItemListPriceRule(Rule::OPERATOR_LT, 21),
            new LineItemListPriceRule(Rule::OPERATOR_EQ, 20),
            new LineItemListPriceRule(Rule::OPERATOR_NEQ, 15),
        ];

        // test different rules for the default list price
        $scope = new LineItemScope($lineItem, $context);
        foreach ($rules as $rule) {
            static::assertTrue($rule->match($scope));
        }

        // create new context for other currency
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', Defaults::SALES_CHANNEL, ['currencyId' => $ids->get('currency')]);

        // fetch cart for recalculation
        $cart = $service->getCart('test', $context, CartService::SALES_CHANNEL, false);
        $lineItem = $cart->get($ids->get('product'));

        $rules = [
            new LineItemListPriceRule(Rule::OPERATOR_GTE, 14),
            new LineItemListPriceRule(Rule::OPERATOR_GT, 14),
            new LineItemListPriceRule(Rule::OPERATOR_LTE, 16),
            new LineItemListPriceRule(Rule::OPERATOR_LT, 16),
            new LineItemListPriceRule(Rule::OPERATOR_EQ, 15),
            new LineItemListPriceRule(Rule::OPERATOR_NEQ, 14),
        ];

        $scope = new LineItemScope($lineItem, $context);
        foreach ($rules as $rule) {
            // test combination with currency rule to validate currency list prices
            $wrapper = new AndRule([
                new CurrencyRule(CurrencyRule::OPERATOR_EQ, $ids->getList(['currency'])),
                $rule,
            ]);

            static::assertTrue($wrapper->match($scope));
        }
    }

    private function createLineItemWithListPrice(float $listPriceAmount): LineItem
    {
        $listPrice = ListPrice::createFromUnitPrice(400, $listPriceAmount);

        return $this->createLineItemWithPrice(LineItem::PRODUCT_LINE_ITEM_TYPE, $listPriceAmount, $listPrice);
    }
}
