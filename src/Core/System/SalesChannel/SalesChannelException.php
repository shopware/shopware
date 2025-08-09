<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class SalesChannelException extends HttpException
{
    final public const SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__SALES_CHANNEL_DOES_NOT_EXISTS';
    final public const LANGUAGE_INVALID_EXCEPTION = 'SYSTEM__LANGUAGE_INVALID_EXCEPTION';
    final public const COUNTRY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_DOES_NOT_EXISTS_EXCEPTION';
    final public const CURRENCY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CURRENCY_DOES_NOT_EXISTS_EXCEPTION';
    final public const COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION';
    final public const TAX_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__TAX_DOES_NOT_EXISTS_EXCEPTION';
    final public const CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION';
    final public const SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION';
    final public const SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION = 'SYSTEM__SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION';
    final public const NO_CONTEXT_DATA_EXCEPTION = 'SYSTEM__NO_CONTEXT_DATA_EXCEPTION';
    final public const LANGUAGE_NOT_FOUND = 'SYSTEM__LANGUAGE_NOT_FOUND';
    final public const SALES_CHANNEL_DOMAIN_IN_USE = 'SYSTEM__SALES_CHANNEL_DOMAIN_IN_USE';
    public const INVALID_TYPE = 'FRAMEWORK__INVALID_TYPE';
    final public const CURRENCY_INVALID_EXCEPTION = 'SYSTEM__CURRENCY_INVALID_EXCEPTION';
    final public const COUNTRY_INVALID_EXCEPTION = 'SYSTEM__COUNTRY_INVALID_EXCEPTION';
    final public const COUNTRY_STATE_INVALID_EXCEPTION = 'SYSTEM__COUNTRY_STATE_INVALID_EXCEPTION';
    final public const SALES_CHANNEL_CONTEXT_PERMISSIONS_LOCKED = 'SYSTEM__SALES_CHANNEL_CONTEXT_PERMISSIONS_LOCKED';
    final public const ENCODING_INVALID_STRUCT_EXCEPTION = 'SYSTEM__ENCODING_INVALID_STRUCT_EXCEPTION';
    final public const ENCODING_MISSING_AGGREGATION_EXCEPTION = 'SYSTEM__ENCODING_MISSING_AGGREGATION_EXCEPTION';
    final public const ORDER_NOT_FOUND_CODE = 'SYSTEM__ORDER_NOT_FOUND_CODE';
    final public const MISSING_ORDER_ASSOCIATION_CODE = 'SYSTEM__MISSING_ORDER_ASSOCIATION_CODE';
    private const INVALID_UUID_MESSAGE_TEMPLATE = 'Provided %s is not a valid UUID';

    public static function salesChannelNotFound(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION,
            'Sales channel with id "{{ salesChannelId }}" not found or not valid!.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function currencyNotFound(string $currencyId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'currency', 'field' => 'id', 'value' => $currencyId]
        );
    }

    public static function countryStateNotFound(string $countryStateId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'country state', 'field' => 'id', 'value' => $countryStateId]
        );
    }

    public static function customerNotFoundByIdException(string $customerId): ShopwareHttpException
    {
        return new CustomerNotFoundByIdException($customerId);
    }

    public static function countryNotFound(string $countryId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'id', 'value' => $countryId]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function orderNotFound(string $orderId): self|OrderException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return OrderException::orderNotFound($orderId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND_CODE,
            self::$couldNotFindMessage,
            ['entity' => 'order', 'field' => 'id', 'value' => $orderId]
        );
    }

    public static function noContextData(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::NO_CONTEXT_DATA_EXCEPTION,
            'No context data found for SalesChannel "{{ salesChannelId }}"',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function invalidLanguageId(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'language ID'),
        );
    }

    public static function languageNotFound(string $languageId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId]
        );
    }

    /**
     * @param array<string> $availableLanguages
     */
    public static function providedLanguageNotAvailable(string $languageId, array $availableLanguages): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION,
            \sprintf('Provided language "%s" is not in list of available languages: %s', $languageId, implode(', ', $availableLanguages)),
        );
    }

    public static function unknownPaymentMethod(string $paymentMethodId): ShopwareHttpException
    {
        return PaymentException::unknownPaymentMethodById($paymentMethodId);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, as the exception is no longer needed, use RestrictDeleteViolationException instead
     */
    public static function salesChannelDomainInUse(?\Throwable $previous = null): ShopwareHttpException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                __CLASS__,
                __METHOD__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_DOMAIN_IN_USE,
            'The sales channel domain cannot be deleted because it is still referenced in product exports.',
            [],
            $previous
        );
    }

    public static function invalidType(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_TYPE,
            $message
        );
    }

    public static function invalidCurrencyId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::CURRENCY_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'currency ID'),
        );
    }

    public static function invalidCountryId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::COUNTRY_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'country ID'),
        );
    }

    public static function invalidCountryStateId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::COUNTRY_STATE_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'country state ID'),
        );
    }

    public static function customerNotLoggedIn(): CartException
    {
        return CartException::customerNotLoggedIn();
    }

    public static function contextPermissionsLocked(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_CONTEXT_PERMISSIONS_LOCKED,
            'Context permission in SalesChannel context already locked.'
        );
    }

    public static function taxNotFound(string $taxId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::TAX_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'tax', 'field' => 'id', 'value' => $taxId]
        );
    }

    public static function customerGroupNotFound(string $customerGroupId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'customer group', 'field' => 'id', 'value' => $customerGroupId]
        );
    }

    public static function shippingMethodNotFound(string $shippingMethodId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'shipping method', 'field' => 'id', 'value' => $shippingMethodId]
        );
    }

    public static function encodingInvalidStructException(string $context): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_INVALID_STRUCT_EXCEPTION,
            'Invalid struct: "{{ context }}"',
            ['context' => $context]
        );
    }

    public static function encodingMissingAggregationException(int|string $key, int $index): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_MISSING_AGGREGATION_EXCEPTION,
            'Can not find encoded aggregation "{{ key }}" for data index "{{ index }}"',
            ['key' => $key, 'index' => $index]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function missingAssociation(string $association): self|OrderException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return OrderException::missingAssociation($association);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_ORDER_ASSOCIATION_CODE,
            'The required association "{{ association }}" is missing .',
            ['association' => $association]
        );
    }
}
