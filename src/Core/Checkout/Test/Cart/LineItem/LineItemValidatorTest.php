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
        $lineItem = new LineItem('Key', 'fake', 1);
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
        $lineItem = new LineItem('Key', 'fake', 1);
        $lineItem->setPrice($this->createMock(CalculatedPrice::class));
        $cart->expects(static::once())->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('Key', $errors->first()->getKey());
        static::assertSame('label', $errors->first()->getMessageKey());
    }

    public function testValidateWithoutPrice(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('Key', 'fake', 1);
        $lineItem->setLabel('Label');
        $cart->expects(static::once())->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('Key', $errors->first()->getKey());
        static::assertSame('price', $errors->first()->getMessageKey());
    }

    public function testValidateWithoutLabelAndPrice(): void
    {
        $cart = $this->createMock(Cart::class);
        $lineItem = new LineItem('Key', 'fake', 1);
        $cart->expects(static::once())->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertInstanceOf(IncompleteLineItemError::class, $errors->first());
        static::assertSame('Key', $errors->first()->getKey());
        static::assertSame('price', $errors->last()->getMessageKey());
    }
}
