<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Calculator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(Calculator::class)]
class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $cashRounding = new CashRounding();
        $percentageTaxRuleBuilder = new PercentageTaxRuleBuilder();
        $quantityPriceCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), $cashRounding),
            new NetPriceCalculator(new TaxCalculator(), $cashRounding),
        );
        $this->calculator = new Calculator(
            $quantityPriceCalculator,
            new PercentagePriceCalculator($cashRounding, $quantityPriceCalculator, $percentageTaxRuleBuilder),
            new AbsolutePriceCalculator($quantityPriceCalculator, $percentageTaxRuleBuilder)
        );

        $this->context = $this->createMock(SalesChannelContext::class);
        $this->context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
    }

    public function testCalculateSimplePrice(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test'))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([])));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(1, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testCalculateQuantityPrice(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test', null, 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(1, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testPercentagePrice(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test', null, 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test'))
            ->setPriceDefinition(new PercentagePriceDefinition(-10));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(2, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('B')->getPrice());
        static::assertSame(-20.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testPercentagePriceWithWrongPriority(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test', null, 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test'))
            ->setPriceDefinition(new PercentagePriceDefinition(-10));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(2, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('B')->getPrice());
        static::assertSame(-20.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testCalculateAbsolutePrice(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test', null, 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test'))
            ->setPriceDefinition(new AbsolutePriceDefinition(-15));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(2, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('B')->getPrice());
        static::assertSame(-15.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testPercentagePriceWithFilter(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('A', 'test'))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection()));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'product', null, 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('C', 'test'))
            ->setPriceDefinition(
                new PercentagePriceDefinition(
                    -10,
                    new AndRule([
                        (new LineItemOfTypeRule())->assign(['lineItemType' => 'product']), ])
                )
            );

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(3, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('B')->getPrice());
        static::assertSame(200.0, $calculated->get('B')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('C'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('C')->getPrice());
        static::assertSame(-20.0, $calculated->get('C')->getPrice()->getTotalPrice());
    }

    public function testAbsolutePriceWithFilter(): void
    {
        $cart = new Cart('test');

        $lineItem = new LineItem('A', 'test');
        $lineItem->setPriceDefinition(
            new QuantityPriceDefinition(100, new TaxRuleCollection([]))
        );
        $cart->add($lineItem);

        $lineItem = new LineItem('B', 'product', null, 2);
        $lineItem->setPriceDefinition(
            new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2)
        );
        $cart->add($lineItem);

        $lineItem = new LineItem('C', 'test');
        $lineItem->setPriceDefinition(
            new AbsolutePriceDefinition(
                -10,
                new AndRule([(new LineItemOfTypeRule())->assign(['lineItemType' => 'product'])])
            )
        );
        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(3, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('B')->getPrice());
        static::assertSame(200.0, $calculated->get('B')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('C'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('C')->getPrice());
        static::assertSame(-10.0, $calculated->get('C')->getPrice()->getTotalPrice());
    }

    public function testNestedLineItemPrice(): void
    {
        $lineItem = new LineItem('A', 'test');

        $product = new LineItem('B', 'product-1');
        $product->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection()));

        $discount = new LineItem('C', 'discount-1');
        $discount->setPriceDefinition(new PercentagePriceDefinition(-10));

        $children = new LineItemCollection([$product, $discount]);

        $lineItem->setChildren($children);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(1, $calculated);
        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(90.0, $calculated->get('A')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('A')->getChildren()->get('B'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getChildren()->get('B')->getPrice());
        static::assertSame(100.0, $calculated->get('A')->getChildren()->get('B')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $calculated->get('A')->getChildren()->get('C'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getChildren()->get('C')->getPrice());
        static::assertSame(-10.0, $calculated->get('A')->getChildren()->get('C')->getPrice()->getTotalPrice());
    }

    public function testDeepNesting(): void
    {
        $cart = new Cart('test');

        $nested = (new LineItem('A', 'container'))->assign([
            'children' => new LineItemCollection([
                (new LineItem('P1', 'product'))->assign([
                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                ]),
                (new LineItem('P2', 'product'))->assign([
                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                ]),
                (new LineItem('D', 'discount'))->assign([
                    'priceDefinition' => new PercentagePriceDefinition(-10),
                ]),
                (new LineItem('B', 'container'))->assign([
                    'children' => new LineItemCollection([
                        (new LineItem('P1', 'product'))->assign([
                            'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                        ]),
                        (new LineItem('P2', 'product'))->assign([
                            'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                        ]),
                        (new LineItem('D', 'discount'))->assign([
                            'priceDefinition' => new PercentagePriceDefinition(-10),
                        ]),
                        (new LineItem('C', 'container'))->assign([
                            'children' => new LineItemCollection([
                                (new LineItem('P1', 'product'))->assign([
                                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                                ]),
                                (new LineItem('P2', 'product'))->assign([
                                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                                ]),
                                (new LineItem('D', 'discount'))->assign([
                                    'priceDefinition' => new PercentagePriceDefinition(-10),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $cart->add($nested);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(1, $calculated);

        $root = $calculated->get('A');
        static::assertInstanceOf(LineItem::class, $root);
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P1'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P1')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P2'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P2')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('D'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('D')->getPrice());
        static::assertSame(-54.2, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        $root = $root->getChildren()->get('B');
        static::assertInstanceOf(LineItem::class, $root);
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P1'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P1')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P2'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P2')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('D'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('D')->getPrice());
        static::assertSame(-38.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        $root = $root->getChildren()->get('C');
        static::assertInstanceOf(LineItem::class, $root);
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P1'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P1')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P2'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P2')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('D'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('D')->getPrice());
        static::assertSame(-20.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        static::assertInstanceOf(LineItem::class, $calculated->get('A'));
        static::assertInstanceOf(CalculatedPrice::class, $calculated->get('A')->getPrice());
        static::assertSame(487.8, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testNoDiscountOfDiscounts(): void
    {
        $cart = new Cart('test');

        $noContainerRule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'container');

        $nested = (new LineItem('A', 'container'))->assign([
            'children' => new LineItemCollection([
                (new LineItem('P1', 'product'))->assign([
                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                ]),
                (new LineItem('D', 'discount'))->assign([
                    'priceDefinition' => new PercentagePriceDefinition(-10, $noContainerRule),
                ]),
                (new LineItem('B', 'container'))->assign([
                    'children' => new LineItemCollection([
                        (new LineItem('P1', 'product'))->assign([
                            'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                        ]),
                        (new LineItem('D', 'discount'))->assign([
                            'priceDefinition' => new PercentagePriceDefinition(-10, $noContainerRule),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $cart->add($nested);

        $calculated = $this->calculator->calculate($cart->getLineItems(), $this->context, new CartBehavior());

        static::assertCount(1, $calculated);

        $root = $calculated->get('A');
        static::assertInstanceOf(LineItem::class, $root);
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P1'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P1')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('D'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('D')->getPrice());
        static::assertSame(-10.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        $root = $root->getChildren()->get('B');
        static::assertInstanceOf(LineItem::class, $root);
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('P1'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('P1')->getPrice());
        static::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        static::assertInstanceOf(LineItem::class, $root->getChildren()->get('D'));
        static::assertInstanceOf(CalculatedPrice::class, $root->getChildren()->get('D')->getPrice());
        static::assertSame(-10.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());
    }
}
