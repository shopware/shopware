<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemValidator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemValidatorTest extends TestCase
{
    public function testValidateEmptyCart(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->expects(static::once())->method('getLineItems')->willReturn(new LineItemCollection());

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $errors);
    }

    public function testValidateWithValidLineItem(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setLabel('Label');
        $lineItem->setPrice($this->createMock(CalculatedPrice::class));
        $cart->expects(static::once())->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $errors);
    }

    public function testValidateWithoutLabel(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setPrice($this->createMock(CalculatedPrice::class));
        $cart->expects(static::exactly(2))->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('id', $errors->first()->getId());
        static::assertSame('label', $errors->first()->getMessageKey());
    }

    public function testValidateWithoutLabelGotRemoved(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setPrice($this->createMock(CalculatedPrice::class));
        $cart->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $cart->getLineItems());
    }

    public function testValidateWithoutPrice(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setLabel('Label');
        $cart->expects(static::exactly(2))->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('id', $errors->first()->getId());
        static::assertSame('price', $errors->first()->getMessageKey());
    }

    public function testValidateWithoutLabelAndPrice(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('id', 'fake');
        $cart->expects(static::exactly(3))->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('id', $errors->first()->getId());
        static::assertSame('price', $errors->last()->getMessageKey());
    }
}
