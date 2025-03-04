<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CartException::class)]
class CartExceptionTest extends TestCase
{
    public function testInvalidPriceFieldType(): void
    {
        $e = CartException::invalidPriceFieldTypeException('badType');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(CartException::INVALID_PRICE_FIELD_TYPE, $e->getErrorCode());
        static::assertSame('The price field does not contain a valid "type" value. Received badType', $e->getMessage());
    }

    public function testDeliveryDateNotSupportedUnit(): void
    {
        $e = CartException::deliveryDateNotSupportedUnit('badUnit');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(CartException::CART_DELIVERY_DATE_NOT_SUPPORTED_UNIT, $e->getErrorCode());
        static::assertSame('Not supported unit badUnit', $e->getMessage());
    }

    public function testShippingMethodNotFound(): void
    {
        $e = CartException::shippingMethodNotFound('shipping-method-id');
        static::assertSame('Could not find shipping method with id "shipping-method-id"', $e->getMessage());
    }

    public function testUnsupportedOperator(): void
    {
        $e = CartException::unsupportedOperator('$', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::RULE_OPERATOR_NOT_SUPPORTED, $e->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $e->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedOperatorDeprecated(): void
    {
        $e = CartException::unsupportedOperator('$', 'testClass');

        static::assertInstanceOf(UnsupportedOperatorException::class, $e);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('CONTENT__RULE_OPERATOR_NOT_SUPPORTED', $e->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $e->getMessage());
    }

    public function testWrongCartDataType(): void
    {
        $fieldKey = 'some-field';
        $expectedType = 'string';
        $e = CartException::wrongCartDataType($fieldKey, $expectedType);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_WRONG_DATA_TYPE, $e->getErrorCode());
        static::assertSame('Cart data some-field does not match expected type "string"', $e->getMessage());
    }
}
