<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @package checkout
 */
#[CoversClass(Validator::class)]
class ValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        $mockValidator = $this->createMock(CartValidatorInterface::class);
        $mockValidator2 = new class($this->createMock(Error::class)) implements CartValidatorInterface {
            public function __construct(private readonly Error $error)
            {
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
        $cart = new Cart('test');

        $mockValidator->expects(static::once())->method('validate')->with($cart, static::anything(), $context);

        $errors = $validator->validate($cart, $context);
        static::assertCount(1, $errors);
    }
}
