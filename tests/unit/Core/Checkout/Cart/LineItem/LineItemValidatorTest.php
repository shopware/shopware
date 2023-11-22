<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemValidator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemValidator::class)]
class LineItemValidatorTest extends TestCase
{
    public function testValidateEmptyCart(): void
    {
        $cart = new Cart('test');

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $errors);
    }

    public function testValidateWithValidLineItem(): void
    {
        $cart = new Cart('test');
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setLabel('Label');
        $lineItem->setPrice(new CalculatedPrice(5, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $cart->add($lineItem);

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $errors);
    }

    public function testValidateWithoutLabel(): void
    {
        $cart = new Cart('test');
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setPrice(new CalculatedPrice(5, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $cart->add($lineItem);

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
        $cart = new Cart('test');
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setPrice(new CalculatedPrice(5, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $cart->add($lineItem);

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $cart->getLineItems());
    }

    public function testValidateWithoutPrice(): void
    {
        $cart = new Cart('test');
        $lineItem = new LineItem('id', 'fake');
        $lineItem->setLabel('Label');
        $cart->add($lineItem);

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
        $cart = new Cart('test');
        $lineItem = new LineItem('id', 'fake');
        $cart->add($lineItem);

        $validator = new LineItemValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $errors);
        static::assertSame('id', $errors->first()?->getId());
        static::assertSame('price', $errors->last()?->getMessageKey());
    }
}
