<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CartException::class)]
class CartExceptionTest extends TestCase
{
    public function testShippingMethodNotFound(): void
    {
        $e = CartException::shippingMethodNotFound('shipping-method-id');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());

        if (Feature::isActive('v6.7.0.0')) {
            static::assertSame(CartException::SHIPPING_METHOD_NOT_FOUND, $e->getErrorCode());
        } else {
            static::assertSame(ShippingException::SHIPPING_METHOD_NOT_FOUND, $e->getErrorCode());
        }

        static::assertSame('Could not find shipping method with id "shipping-method-id"', $e->getMessage());
    }

    public function testDeserializeFailed(): void
    {
        $e = CartException::deserializeFailed();

        static::assertSame(400, $e->getStatusCode());
        static::assertSame(CartException::DESERIALIZE_FAILED_CODE, $e->getErrorCode());
        static::assertSame('Failed to deserialize cart.', $e->getMessage());
    }

    public function testTokenNotFound(): void
    {
        $token = 'some-token';
        $e = CartException::tokenNotFound($token);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::TOKEN_NOT_FOUND_CODE, $e->getErrorCode());
        static::assertSame('Cart with token some-token not found.', $e->getMessage());
    }

    public function testCustomerNotLoggedIn(): void
    {
        $e = CartException::customerNotLoggedIn();

        static::assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
        static::assertSame(CartException::CUSTOMER_NOT_LOGGED_IN_CODE, $e->getErrorCode());
        static::assertSame('Customer is not logged in.', $e->getMessage());
    }

    public function testInsufficientPermission(): void
    {
        $e = CartException::insufficientPermission();

        static::assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
        static::assertSame(CartException::INSUFFICIENT_PERMISSION_CODE, $e->getErrorCode());
        static::assertSame('Insufficient permission.', $e->getMessage());
    }

    public function testInvalidPaymentButOrderStored(): void
    {
        $orderId = 'order123';
        $e = CartException::invalidPaymentButOrderStored($orderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::CART_PAYMENT_INVALID_ORDER_STORED_CODE, $e->getErrorCode());
        static::assertSame('Order payment failed but order was stored with id order123.', $e->getMessage());
    }

    public function testInvalidPaymentOrderNotStored(): void
    {
        $orderId = 'order123';
        $e = CartException::invalidPaymentOrderNotStored($orderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::CART_PAYMENT_INVALID_ORDER_CODE, $e->getErrorCode());
        static::assertSame('Order payment failed. The order was not stored.', $e->getMessage());
    }

    public function testOrderNotFound(): void
    {
        $orderId = 'order123';
        $e = CartException::orderNotFound($orderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::CART_ORDER_CONVERT_NOT_FOUND_CODE, $e->getErrorCode());
        static::assertSame('Order order123 could not be found.', $e->getMessage());
    }

    public function testInvalidCart(): void
    {
        $errors = new ErrorCollection();
        $errors->add(new GenericCartError('error1', 'message1', [], Error::LEVEL_ERROR, false, false, false));
        $e = CartException::invalidCart($errors);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_CODE, $e->getErrorCode());
        static::assertSame('The cart is invalid, got 1 error(s): error1: ', $e->getMessage());
    }

    public function testInvalidChildQuantity(): void
    {
        $childQuantity = 2;
        $parentQuantity = 3;
        $e = CartException::invalidChildQuantity($childQuantity, $parentQuantity);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_LINE_ITEM_QUANTITY_CODE, $e->getErrorCode());
        static::assertSame('The quantity of a child "2" must be a multiple of the parent quantity "3"', $e->getMessage());
    }

    public function testInvalidPayload(): void
    {
        $key = 'key';
        $id = 'id';
        $e = CartException::invalidPayload($key, $id);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_LINE_ITEM_PAYLOAD_CODE, $e->getErrorCode());
        static::assertSame('Unable to save payload with key `key` on line item `id`. Only scalar data types are allowed.', $e->getMessage());
    }

    public function testInvalidQuantity(): void
    {
        $quantity = 5;
        $e = CartException::invalidQuantity($quantity);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_LINE_ITEM_QUANTITY_CODE, $e->getErrorCode());
        static::assertSame('The quantity must be a positive integer. Given: "5"', $e->getMessage());
    }

    public function testDeliveryNotFound(): void
    {
        $id = 'delivery123';
        $e = CartException::deliveryNotFound($id);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::CART_DELIVERY_NOT_FOUND_CODE, $e->getErrorCode());
        static::assertSame('Delivery with identifier delivery123 not found.', $e->getMessage());
    }

    public function testLineItemNotFound(): void
    {
        $id = 'item123';
        $e = CartException::lineItemNotFound($id);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_NOT_FOUND_CODE, $e->getErrorCode());
        static::assertSame('Line item with identifier item123 not found.', $e->getMessage());
    }

    public function testLineItemNotRemovable(): void
    {
        $id = 'item123';
        $e = CartException::lineItemNotRemovable($id);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_NOT_REMOVABLE_CODE, $e->getErrorCode());
        static::assertSame('Line item with identifier item123 is not removable.', $e->getMessage());
    }

    public function testLineItemNotStackable(): void
    {
        $id = 'item123';
        $e = CartException::lineItemNotStackable($id);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_NOT_STACKABLE_CODE, $e->getErrorCode());
        static::assertSame('Line item with identifier "item123" is not stackable and the quantity cannot be changed.', $e->getMessage());
    }

    public function testLineItemTypeNotSupported(): void
    {
        $type = 'unsupported';
        $e = CartException::lineItemTypeNotSupported($type);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_TYPE_NOT_SUPPORTED_CODE, $e->getErrorCode());
        static::assertSame('Line item type "unsupported" is not supported.', $e->getMessage());
    }

    public function testLineItemTypeNotUpdatable(): void
    {
        $type = 'not-updatable';
        $e = CartException::lineItemTypeNotUpdatable($type);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_TYPE_NOT_UPDATABLE_CODE, $e->getErrorCode());
        static::assertSame('Line item type "not-updatable" cannot be updated.', $e->getMessage());
    }

    public function testMissingLineItemPrice(): void
    {
        $id = 'item123';
        $e = CartException::missingLineItemPrice($id);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_MISSING_LINE_ITEM_PRICE_CODE, $e->getErrorCode());
        static::assertSame('Line item with identifier item123 has no price.', $e->getMessage());
    }

    public function testInvalidPriceDefinition(): void
    {
        $e = CartException::invalidPriceDefinition();

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_PRICE_DEFINITION_CODE, $e->getErrorCode());
        static::assertSame('Provided price definition is invalid.', $e->getMessage());
    }

    public function testMixedLineItemType(): void
    {
        $id = 'item123';
        $type = 'different-type';
        $e = CartException::mixedLineItemType($id, $type);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_MIXED_LINE_ITEM_TYPE_CODE, $e->getErrorCode());
        static::assertSame('Line item with id item123 already exists with different type different-type.', $e->getMessage());
    }

    public function testPayloadKeyNotFound(): void
    {
        $key = 'some-key';
        $lineItemId = 'item123';
        $e = CartException::payloadKeyNotFound($key, $lineItemId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_PAYLOAD_KEY_NOT_FOUND_CODE, $e->getErrorCode());
        static::assertSame('Payload key "some-key" in line item "item123" not found.', $e->getMessage());
    }

    public function testInvalidPercentageDiscount(): void
    {
        $key = 'discount-key';
        $e = CartException::invalidPercentageDiscount($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_PERCENTAGE_DISCOUNT_CODE, $e->getErrorCode());
        static::assertSame('Percentage discount discount-key requires a provided float value', $e->getMessage());
    }

    public function testDiscountTypeNotSupported(): void
    {
        $key = 'discount-key';
        $type = 'unsupported-type';
        $e = CartException::discountTypeNotSupported($key, $type);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_DISCOUNT_TYPE_NOT_SUPPORTED_CODE, $e->getErrorCode());
        static::assertSame('Discount type "unsupported-type" is not supported for discount discount-key', $e->getMessage());
    }

    public function testAbsoluteDiscountMissingPriceCollection(): void
    {
        $key = 'discount-key';
        $e = CartException::absoluteDiscountMissingPriceCollection($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_ABSOLUTE_DISCOUNT_MISSING_PRICE_COLLECTION_CODE, $e->getErrorCode());
        static::assertSame('Absolute discount discount-key requires a provided price collection. Use services.price(...) to create a price', $e->getMessage());
    }

    public function testMissingDefaultPriceCollectionForDiscount(): void
    {
        $key = 'discount-key';
        $e = CartException::missingDefaultPriceCollectionForDiscount($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_DISCOUNT_CODE, $e->getErrorCode());
        static::assertSame('Absolute discount discount-key requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object', $e->getMessage());
    }

    public function testInvalidPercentageSurcharge(): void
    {
        $key = 'surcharge-key';
        $e = CartException::invalidPercentageSurcharge($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_INVALID_PERCENTAGE_SURCHARGE_CODE, $e->getErrorCode());
        static::assertSame('Percentage surcharge surcharge-key requires a provided float value', $e->getMessage());
    }

    public function testSurchargeTypeNotSupported(): void
    {
        $key = 'surcharge-key';
        $type = 'unsupported-type';
        $e = CartException::surchargeTypeNotSupported($key, $type);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_SURCHARGE_TYPE_NOT_SUPPORTED_CODE, $e->getErrorCode());
        static::assertSame('Surcharge type "unsupported-type" is not supported for surcharge surcharge-key', $e->getMessage());
    }

    public function testAbsoluteSurchargeMissingPriceCollection(): void
    {
        $key = 'surcharge-key';
        $e = CartException::absoluteSurchargeMissingPriceCollection($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_ABSOLUTE_SURCHARGE_MISSING_PRICE_COLLECTION_CODE, $e->getErrorCode());
        static::assertSame('Absolute surcharge surcharge-key requires a provided price collection. Use services.price(...) to create a price', $e->getMessage());
    }

    public function testMissingDefaultPriceCollectionForSurcharge(): void
    {
        $key = 'surcharge-key';
        $e = CartException::missingDefaultPriceCollectionForSurcharge($key);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_SURCHARGE_CODE, $e->getErrorCode());
        static::assertSame('Absolute surcharge surcharge-key requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object', $e->getMessage());
    }

    public function testMissingCartBehavior(): void
    {
        $e = CartException::missingCartBehavior();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_MISSING_BEHAVIOR_CODE, $e->getErrorCode());
        static::assertSame('Cart instance of the cart facade were never calculated. Please call calculate() before using the cart facade.', $e->getMessage());
    }

    public function testTaxRuleNotFound(): void
    {
        $taxId = 'tax123';
        $e = CartException::taxRuleNotFound($taxId);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(CartException::TAX_ID_NOT_FOUND, $e->getErrorCode());
        static::assertSame('Tax rule with id "tax123" not found.', $e->getMessage());
    }

    public function testTaxIdParameterIsMissing(): void
    {
        $e = CartException::taxIdParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::TAX_ID_PARAMETER_IS_MISSING, $e->getErrorCode());
        static::assertSame('Parameter "taxId" is missing.', $e->getMessage());
    }

    public function testPriceParameterIsMissing(): void
    {
        $e = CartException::priceParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::PRICE_PARAMETER_IS_MISSING, $e->getErrorCode());
        static::assertSame('Parameter "price" is missing.', $e->getMessage());
    }

    public function testPricesParameterIsMissing(): void
    {
        $e = CartException::pricesParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::PRICES_PARAMETER_IS_MISSING, $e->getErrorCode());
        static::assertSame('Parameter "prices" is missing.', $e->getMessage());
    }

    public function testLineItemInvalid(): void
    {
        $reason = 'Some reason';
        $e = CartException::lineItemInvalid($reason);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::CART_LINE_ITEM_INVALID, $e->getErrorCode());
        static::assertSame('Line item is invalid: ' . $reason, $e->getMessage());
    }

    public function testUnsupportedValue(): void
    {
        $type = 'some-type';
        $class = 'some-class';
        $e = CartException::unsupportedValue($type, $class);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(CartException::VALUE_NOT_SUPPORTED, $e->getErrorCode());
        static::assertSame('Unsupported value of type some-type in some-class', $e->getMessage());
    }

    public function testAddressNotFound(): void
    {
        $id = 'address123';
        $e = CartException::addressNotFound($id);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('Customer address with id "address123" not found.', $e->getMessage());
    }

    public function testHashMismatch(): void
    {
        $token = 'some-token';
        $e = CartException::hashMismatch($token);

        static::assertSame(Response::HTTP_CONFLICT, $e->getStatusCode());
        static::assertSame(CartException::CART_HASH_MISMATCH, $e->getErrorCode());
        static::assertSame('Content hash mismatch for cart token: some-token', $e->getMessage());
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
