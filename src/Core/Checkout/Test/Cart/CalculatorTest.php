<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Calculator;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class CalculatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var CheckoutContextFactory
     */
    private $factory;

    /**
     * @var Calculator
     */
    private $calculator;

    /**
     * @var CheckoutContext
     */
    private $context;

    protected function setUp()
    {
        $this->calculator = $this->getContainer()->get(Calculator::class);
        $this->factory = $this->getContainer()->get(CheckoutContextFactory::class);
        $this->context = $this->factory->create(Defaults::TENANT_ID, Defaults::TENANT_ID, Defaults::SALES_CHANNEL);
    }

    public function testCalculateSimplePrice()
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 1))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([])));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(1, $calculated);
        self::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testCalculateQuantityPrice()
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(1, $calculated);
        self::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }

    public function testPercentagePrice(): void
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test', 1))
            ->setPriceDefinition(new PercentagePriceDefinition(-10, null));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(2, $calculated);
        self::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(-20.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testPercentagePriceWithWrongPriority()
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 2))
            ->setPriority(LineItem::GOODS_PRIORITY)
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test', 1))
            ->setPriority(LineItem::GOODS_PRIORITY + 1)
            ->setPriceDefinition(new PercentagePriceDefinition(-10, null));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(2, $calculated);
        self::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(0.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testCalculateAbsolutePrice(): void
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'test', 1))
            ->setPriceDefinition(new AbsolutePriceDefinition(-15, null));

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(2, $calculated);
        self::assertSame(200.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(-15.0, $calculated->get('B')->getPrice()->getTotalPrice());
    }

    public function testPercentagePriceWithFilter()
    {
        $cart = new Cart('test', 'test');

        $lineItem = (new LineItem('A', 'test', 1))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 1));

        $cart->add($lineItem);

        $lineItem = (new LineItem('B', 'product', 2))
            ->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2));

        $cart->add($lineItem);

        $lineItem = (new LineItem('C', 'test', 1))
            ->setPriceDefinition(
                new PercentagePriceDefinition(
                    -10,
                    new AndRule([
                    new LineItemOfTypeRule('product'), ])
                )
            );

        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(3, $calculated);
        self::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(200.0, $calculated->get('B')->getPrice()->getTotalPrice());
        self::assertSame(-20.0, $calculated->get('C')->getPrice()->getTotalPrice());
    }

    public function testAbsolutePriceWithFilter()
    {
        $cart = new Cart('test', 'test');

        $lineItem = new LineItem('A', 'test', 1);
        $lineItem->setPriceDefinition(
            new QuantityPriceDefinition(100, new TaxRuleCollection([]), 1)
        );
        $cart->add($lineItem);

        $lineItem = new LineItem('B', 'product', 2);
        $lineItem->setPriceDefinition(
            new QuantityPriceDefinition(100, new TaxRuleCollection([]), 2)
        );
        $cart->add($lineItem);

        $lineItem = new LineItem('C', 'test', 1);
        $lineItem->setPriceDefinition(
            new AbsolutePriceDefinition(
                -10,
                new AndRule([new LineItemOfTypeRule('product')])
            )
        );
        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(3, $calculated);
        self::assertSame(100.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(200.0, $calculated->get('B')->getPrice()->getTotalPrice());
        self::assertSame(-10.0, $calculated->get('C')->getPrice()->getTotalPrice());
    }

    public function testNestedLineItemPrice()
    {
        $lineItem = new LineItem('A', 'test');

        $product = new LineItem('B', 'product-1');
        $product->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection()));

        $discount = new LineItem('C', 'discount-1');
        $discount->setPriceDefinition(new PercentagePriceDefinition(-10, null));

        $children = new LineItemCollection([$product, $discount]);

        $lineItem->setChildren($children);

        $cart = new Cart('test', 'test');
        $cart->add($lineItem);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(1, $calculated);
        self::assertSame(90.0, $calculated->get('A')->getPrice()->getTotalPrice());
        self::assertSame(100.0, $calculated->get('A')->getChildren()->get('B')->getPrice()->getTotalPrice());
        self::assertSame(-10.0, $calculated->get('A')->getChildren()->get('C')->getPrice()->getTotalPrice());
    }

    public function testDeepNesting(): void
    {
        $cart = new Cart('test', 'test');

        $nested = (new LineItem('A', 'container', 1))->assign([
            'children' => new LineItemCollection([
                (new LineItem('P1', 'product', 1))->assign([
                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                ]),
                (new LineItem('P2', 'product', 1))->assign([
                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                ]),
                (new LineItem('D', 'discount', 1))->assign([
                    'priceDefinition' => new PercentagePriceDefinition(-10, null),
                ]),
                (new LineItem('B', 'container', 1))->assign([
                    'children' => new LineItemCollection([
                        (new LineItem('P1', 'product', 1))->assign([
                            'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                        ]),
                        (new LineItem('P2', 'product', 1))->assign([
                            'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                        ]),
                        (new LineItem('D', 'discount', 1))->assign([
                            'priceDefinition' => new PercentagePriceDefinition(-10, null),
                        ]),
                        (new LineItem('C', 'container', 1))->assign([
                            'children' => new LineItemCollection([
                                (new LineItem('P1', 'product', 1))->assign([
                                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                                ]),
                                (new LineItem('P2', 'product', 1))->assign([
                                    'priceDefinition' => new QuantityPriceDefinition(100, new TaxRuleCollection()),
                                ]),
                                (new LineItem('D', 'discount', 1))->assign([
                                    'priceDefinition' => new PercentagePriceDefinition(-10, null),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $cart->add($nested);

        $calculated = $this->calculator->calculate($cart, $this->context);

        self::assertCount(1, $calculated);

        $root = $calculated->get('A');
        self::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        self::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        self::assertSame(-20.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        $root = $root->getChildren()->get('B');
        self::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        self::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        self::assertSame(-20.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        $root = $root->getChildren()->get('C');
        self::assertSame(100.0, $root->getChildren()->get('P1')->getPrice()->getTotalPrice());
        self::assertSame(100.0, $root->getChildren()->get('P2')->getPrice()->getTotalPrice());
        self::assertSame(-20.0, $root->getChildren()->get('D')->getPrice()->getTotalPrice());

        self::assertSame(540.0, $calculated->get('A')->getPrice()->getTotalPrice());
    }
}
