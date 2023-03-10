<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\Facade\CartFacade;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\CartPriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\Facade\DiscountFacade;
use Shopware\Core\Checkout\Cart\Facade\ErrorsFacade;
use Shopware\Core\Checkout\Cart\Facade\ItemsFacade;
use Shopware\Core\Checkout\Cart\Facade\ProductsFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Facade\StatesFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Facade\CartFacade
 */
class CartFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $cart = new Cart('foo');
        $cart->setLineItems(new LineItemCollection([
            new LineItem('item', 'item'),
            new LineItem('discount', 'discount'),
            new LineItem('product', 'product'),
        ]));

        $cart->addState('within-test');

        $cart->setPrice(new CartPrice(
            100,
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $cart->setErrors(new ErrorCollection([
            new GenericCartError('foo', 'foo', [], 1, false, false),
        ]));

        $facade = new CartFacade(
            $this->createMock(CartFacadeHelper::class),
            $this->createMock(ScriptPriceStubs::class),
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        $items = $facade->items();
        static::assertInstanceOf(ItemsFacade::class, $items);
        static::assertEquals(3, $items->count());
        static::assertIsIterable($items);
        static::assertTrue($items->has('item'));

        $items = $facade->products();
        static::assertInstanceOf(ProductsFacade::class, $items);
        static::assertEquals(3, $items->count());
        static::assertIsIterable($items);

        $price = $facade->price();
        static::assertInstanceOf(CartPriceFacade::class, $price);
        static::assertEquals(100, $price->getTotal());

        $errors = $facade->errors();
        static::assertInstanceOf(ErrorsFacade::class, $errors);
        static::assertIsIterable($errors);
        static::assertCount(1, $errors);

        static::assertInstanceOf(ContainerFacade::class, $facade->container('my-container'));
        static::assertEquals(3, $facade->count());
        static::assertTrue($cart->has('item'));
        static::assertInstanceOf(LineItem::class, $cart->get('item'));

        $states = $facade->states();
        static::assertInstanceOf(StatesFacade::class, $states);
        static::assertTrue($states->has('within-test'));

        static::assertInstanceOf(DiscountFacade::class, $facade->discount('my-discount', 'percentage', 10, 'my-discount'));
        static::assertTrue($facade->has('my-discount'));

        static::assertInstanceOf(DiscountFacade::class, $facade->discount('my-surcharge', 'percentage', 10, 'my-surcharge'));
        static::assertTrue($facade->has('my-surcharge'));
    }

    public function testCalculateRequiresABehavior(): void
    {
        $facade = new CartFacade(
            $this->createMock(CartFacadeHelper::class),
            $this->createMock(ScriptPriceStubs::class),
            $this->createMock(Cart::class),
            $this->createMock(SalesChannelContext::class)
        );

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Cart instance of the cart facade were never calculated. Please call calculate() before using the cart facade.');

        $facade->calculate();
    }

    public function testCalculateWorksWhenBehaviorIsGiven(): void
    {
        $cart = new Cart('foo');
        $cart->setBehavior(new CartBehavior());

        $helper = $this->createMock(CartFacadeHelper::class);
        $helper->expects(static::once())->method('calculate');

        $facade = new CartFacade(
            $helper,
            $this->createMock(ScriptPriceStubs::class),
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        $facade->calculate();

        static::assertTrue(true);
    }
}
