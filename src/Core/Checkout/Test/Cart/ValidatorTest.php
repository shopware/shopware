<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        $mockValidator = $this->createMock(CartValidatorInterface::class);
        $mockValidator2 = new class($this->createMock(Error::class)) implements CartValidatorInterface {
            private Error $error;

            public function __construct(Error $error)
            {
                $this->error = $error;
            }

            public function validate(
                Cart $cart,
                ErrorCollection $errors,
                SalesChannelContext $context
            ): void {
                $errors->add($this->error);
            }
        };
        $validator = new Validator([$mockValidator, $mockValidator2]);
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);

        $mockValidator->expects(static::once())->method('validate')->with($cart, static::anything(), $context);

        $errors = $validator->validate($cart, $context);
        static::assertCount(1, $errors);
    }
}
