<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CartException extends HttpException
{
    public const DESERIALIZE_FAILED_CODE = 'CHECKOUT__CART_DESERIALIZE_FAILED';
    public const TOKEN_NOT_FOUND_CODE = 'CHECKOUT__CART_TOKEN_NOT_FOUND';
    public const CUSTOMER_NOT_LOGGED_IN_CODE = 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN';
    public const INSUFFICIENT_PERMISSION_CODE = 'CHECKOUT__INSUFFICIENT_PERMISSION';
    public const CART_DELIVERY_NOT_FOUND_CODE = 'CHECKOUT__CART_DELIVERY_POSITION_NOT_FOUND';
    public const CART_INVALID_CODE = 'CHECKOUT__CART_INVALID';
    public const CART_INVALID_LINE_ITEM_PAYLOAD_CODE = 'CHECKOUT__CART_INVALID_LINE_ITEM_PAYLOAD';
    public const CART_INVALID_LINE_ITEM_QUANTITY_CODE = 'CHECKOUT__CART_INVALID_LINE_ITEM_QUANTITY';
    public const CART_LINE_ITEM_NOT_FOUND_CODE = 'CHECKOUT__CART_LINE_ITEM_NOT_FOUND';
    public const CART_LINE_ITEM_NOT_REMOVABLE_CODE = 'CHECKOUT__CART_LINE_ITEM_NOT_REMOVABLE';
    public const CART_LINE_ITEM_NOT_STACKABLE_CODE = 'CHECKOUT__CART_LINE_ITEM_NOT_STACKABLE';
    public const CART_LINE_ITEM_TYPE_NOT_SUPPORTED_CODE = 'CHECKOUT__CART_LINE_ITEM_TYPE_NOT_SUPPORTED';
    public const CART_MISSING_LINE_ITEM_PRICE_CODE = 'CHECKOUT__CART_MISSING_LINE_ITEM_PRICE';
    public const CART_INVALID_PRICE_DEFINITION_CODE = 'CHECKOUT__CART_MISSING_PRICE_DEFINITION';
    public const CART_MIXED_LINE_ITEM_TYPE_CODE = 'CHECKOUT__CART_MIXED_LINE_ITEM_TYPE';
    public const CART_PAYLOAD_KEY_NOT_FOUND_CODE = 'CHECKOUT__CART_PAYLOAD_KEY_NOT_FOUND';
    public const CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_DISCOUNT_CODE = 'CHECKOUT__CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_DISCOUNT';
    public const CART_ABSOLUTE_DISCOUNT_MISSING_PRICE_COLLECTION_CODE = 'CHECKOUT__CART_ABSOLUTE_DISCOUNT_MISSING_PRICE_COLLECTION';
    public const CART_DISCOUNT_TYPE_NOT_SUPPORTED_CODE = 'CHECKOUT__CART_DISCOUNT_TYPE_NOT_SUPPORTED';
    public const CART_INVALID_PERCENTAGE_DISCOUNT_CODE = 'CHECKOUT__CART_INVALID_PERCENTAGE_DISCOUNT';
    public const CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_SURCHARGE_CODE = 'CHECKOUT__CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_SURCHARGE';
    public const CART_ABSOLUTE_SURCHARGE_MISSING_PRICE_COLLECTION_CODE = 'CHECKOUT__CART_ABSOLUTE_SURCHARGE_MISSING_PRICE_COLLECTION';
    public const CART_SURCHARGE_TYPE_NOT_SUPPORTED_CODE = 'CHECKOUT__CART_SURCHARGE_TYPE_NOT_SUPPORTED';
    public const CART_INVALID_PERCENTAGE_SURCHARGE_CODE = 'CHECKOUT__CART_INVALID_PERCENTAGE_SURCHARGE';
    public const CART_MISSING_BEHAVIOR_CODE = 'CHECKOUT__CART_MISSING_BEHAVIOR';

    public static function deserializeFailed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DESERIALIZE_FAILED_CODE,
            'Failed to deserialize cart.'
        );
    }

    public static function tokenNotFound(string $token): self
    {
        return new CartTokenNotFoundException(Response::HTTP_NOT_FOUND, self::TOKEN_NOT_FOUND_CODE, 'Cart with token {{ token }} not found.', ['token' => $token]);
    }

    public static function customerNotLoggedIn(): self
    {
        return new CustomerNotLoggedInException(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }

    public static function insufficientPermission(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::INSUFFICIENT_PERMISSION_CODE,
            'Insufficient permission.'
        );
    }

    /**
     * @return CartException|InvalidCartException
     */
    public static function invalidCart(ErrorCollection $errors)
    {
        $message = [];
        foreach ($errors as $error) {
            $message[] = $error->getId() . ': ' . $error->getMessage();
        }

        return new InvalidCartException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CART_INVALID_CODE,
            'The cart is invalid, got {{ errorCount }} error(s): {{ errors }}',
            ['errorCount' => $errors->count(), 'errors' => implode(\PHP_EOL, $message)]
        );
    }

    public static function invalidChildQuantity(int $childQuantity, int $parentQuantity): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_INVALID_LINE_ITEM_QUANTITY_CODE,
            'The quantity of a child "{{ childQuantity }}" must be a multiple of the parent quantity "{{ parentQuantity }}"',
            ['childQuantity' => $childQuantity, 'parentQuantity' => $parentQuantity]
        );
    }

    public static function invalidPayload(string $key, string $id): CartException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_INVALID_LINE_ITEM_PAYLOAD_CODE,
            'Unable to save payload with key `{{ key }}` on line item `{{ id }}`. Only scalar data types are allowed.',
            ['key' => $key, 'id' => $id]
        );
    }

    public static function invalidQuantity(int $quantity): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_INVALID_LINE_ITEM_QUANTITY_CODE,
            'The quantity must be a positive integer. Given: "{{ quantity }}"',
            ['quantity' => $quantity]
        );
    }

    public static function deliveryNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CART_DELIVERY_NOT_FOUND_CODE,
            'Delivery with identifier {{ id }} not found.',
            ['id' => $id]
        );
    }

    public static function lineItemNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CART_LINE_ITEM_NOT_FOUND_CODE,
            'Line item with identifier {{ id }} not found.',
            ['id' => $id]
        );
    }

    public static function lineItemNotRemovable(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_LINE_ITEM_NOT_REMOVABLE_CODE,
            'Line item with identifier {{ id }} is not removable.',
            ['id' => $id]
        );
    }

    public static function lineItemNotStackable(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_LINE_ITEM_NOT_STACKABLE_CODE,
            'Line item with identifier "{{ id }}" is not stackable and the quantity cannot be changed.',
            ['id' => $id]
        );
    }

    public static function lineItemTypeNotSupported(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_LINE_ITEM_TYPE_NOT_SUPPORTED_CODE,
            'Line item type "{{ type }}" is not supported.',
            ['type' => $type]
        );
    }

    public static function missingLineItemPrice(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_MISSING_LINE_ITEM_PRICE_CODE,
            'Line item with identifier {{ id }} has no price.',
            ['id' => $id]
        );
    }

    public static function invalidPriceDefinition(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_INVALID_PRICE_DEFINITION_CODE,
            'Provided price definition is invalid.'
        );
    }

    public static function mixedLineItemType(string $id, string $type): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_MIXED_LINE_ITEM_TYPE_CODE,
            'Line item with id {{ id }} already exists with different type {{ type }}.',
            ['id' => $id, 'type' => $type]
        );
    }

    public static function payloadKeyNotFound(string $key, string $lineItemId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_PAYLOAD_KEY_NOT_FOUND_CODE,
            'Payload key "{{ key }}" in line item "{{ id }}" not found.',
            ['key' => $key, 'id' => $lineItemId]
        );
    }

    public static function invalidPercentageDiscount(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_INVALID_PERCENTAGE_DISCOUNT_CODE,
            'Percentage discount {{ key }} requires a provided float value',
            ['key' => $key]
        );
    }

    public static function discountTypeNotSupported(string $key, string $type): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_DISCOUNT_TYPE_NOT_SUPPORTED_CODE,
            'Discount type "{{ type }}" is not supported for discount {{ key }}',
            ['key' => $key, 'type' => $type]
        );
    }

    public static function absoluteDiscountMissingPriceCollection(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_ABSOLUTE_DISCOUNT_MISSING_PRICE_COLLECTION_CODE,
            'Absolute discount {{ key }} requires a provided price collection. Use services.price(...) to create a price',
            ['key' => $key]
        );
    }

    public static function missingDefaultPriceCollectionForDiscount(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_DISCOUNT_CODE,
            'Absolute discount {{ key }} requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object',
            ['key' => $key]
        );
    }

    public static function invalidPercentageSurcharge(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_INVALID_PERCENTAGE_SURCHARGE_CODE,
            'Percentage surcharge {{ key }} requires a provided float value',
            ['key' => $key]
        );
    }

    public static function surchargeTypeNotSupported(string $key, string $type): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_SURCHARGE_TYPE_NOT_SUPPORTED_CODE,
            'Surcharge type "{{ type }}" is not supported for surcharge {{ key }}',
            ['key' => $key, 'type' => $type]
        );
    }

    public static function absoluteSurchargeMissingPriceCollection(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_ABSOLUTE_SURCHARGE_MISSING_PRICE_COLLECTION_CODE,
            'Absolute surcharge {{ key }} requires a provided price collection. Use services.price(...) to create a price',
            ['key' => $key]
        );
    }

    public static function missingDefaultPriceCollectionForSurcharge(string $key): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CART_MISSING_DEFAULT_PRICE_COLLECTION_FOR_SURCHARGE_CODE,
            'Absolute surcharge {{ key }} requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object',
            ['key' => $key]
        );
    }

    public static function missingCartBehavior(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CART_MISSING_BEHAVIOR_CODE,
            'Cart instance of the cart facade were never calculated. Please call calculate() before using the cart facade.'
        );
    }
}
