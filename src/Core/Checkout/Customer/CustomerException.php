<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerRecoveryHashExpiredException;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\DuplicateWishlistProductException;
use Shopware\Core\Checkout\Customer\Exception\InvalidImitateCustomerTokenException;
use Shopware\Core\Checkout\Customer\Exception\PasswordPoliciesUpdatedException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerException extends HttpException
{
    /**
     * @deprecated tag:v6.7.0 - Constant CUSTOMER_IS_INACTIVE will be removed as it is unused
     */
    public const CUSTOMER_IS_INACTIVE = 'CHECKOUT__CUSTOMER_IS_INACTIVE';

    public const CUSTOMERS_NOT_FOUND = 'CHECKOUT__CUSTOMERS_NOT_FOUND';
    public const CUSTOMER_NOT_FOUND = 'CHECKOUT__CUSTOMER_NOT_FOUND';
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';
    public const CUSTOMER_GROUP_REQUEST_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_REQUEST_NOT_FOUND';
    public const CUSTOMER_NOT_LOGGED_IN = 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN';
    public const LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND = 'CHECKOUT__LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND';
    public const CUSTOMER_IDS_PARAMETER_IS_MISSING = 'CHECKOUT__CUSTOMER_IDS_PARAMETER_IS_MISSING';
    public const PRODUCT_IDS_PARAMETER_IS_MISSING = 'CHECKOUT__PRODUCT_IDS_PARAMETER_IS_MISSING';
    public const CUSTOMER_ADDRESS_NOT_FOUND = 'CHECKOUT__CUSTOMER_ADDRESS_NOT_FOUND';
    public const CUSTOMER_AUTH_BAD_CREDENTIALS = 'CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS';
    public const CUSTOMER_ADDRESS_IS_ACTIVE = 'CHECKOUT__CUSTOMER_ADDRESS_IS_ACTIVE';
    public const CUSTOMER_ADDRESS_IS_DEFAULT = 'CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT';
    public const CUSTOMER_IS_ALREADY_CONFIRMED = 'CHECKOUT__CUSTOMER_IS_ALREADY_CONFIRMED';
    public const CUSTOMER_GROUP_REGISTRATION_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_REGISTRATION_NOT_FOUND';
    public const CUSTOMER_NOT_FOUND_BY_HASH = 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_HASH';
    public const CUSTOMER_NOT_FOUND_BY_ID = 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_ID';
    public const CUSTOMER_RECOVERY_HASH_EXPIRED = 'CHECKOUT__CUSTOMER_RECOVERY_HASH_EXPIRED';
    public const WISHLIST_IS_NOT_ACTIVATED = 'CHECKOUT__WISHLIST_IS_NOT_ACTIVATED';
    public const WISHLIST_NOT_FOUND = 'CHECKOUT__WISHLIST_NOT_FOUND';
    public const COUNTRY_NOT_FOUND = 'CHECKOUT__CUSTOMER_COUNTRY_NOT_FOUND';
    public const DUPLICATE_WISHLIST_PRODUCT = 'CHECKOUT__DUPLICATE_WISHLIST_PRODUCT';
    public const LEGACY_PASSWORD_ENCODER_NOT_FOUND = 'CHECKOUT__LEGACY_PASSWORD_ENCODER_NOT_FOUND';
    public const NO_HASH_PROVIDED = 'CHECKOUT__NO_HASH_PROVIDED';
    public const WISHLIST_PRODUCT_NOT_FOUND = 'CHECKOUT__WISHLIST_PRODUCT_NOT_FOUND';
    public const CUSTOMER_AUTH_THROTTLED = 'CHECKOUT__CUSTOMER_AUTH_THROTTLED';
    public const CUSTOMER_OPTIN_NOT_COMPLETED = 'CHECKOUT__CUSTOMER_OPTIN_NOT_COMPLETED';
    public const CUSTOMER_CHANGE_PAYMENT_ERROR = 'CHECKOUT__CUSTOMER_CHANGE_PAYMENT_METHOD_NOT_FOUND';
    public const CUSTOMER_GUEST_AUTH_INVALID = 'CHECKOUT__CUSTOMER_AUTH_INVALID';
    public const IMITATE_CUSTOMER_INVALID_TOKEN = 'CHECKOUT__IMITATE_CUSTOMER_INVALID_TOKEN';

    public static function customerGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'customer group', 'field' => 'id', 'value' => $id]
        );
    }

    public static function groupRequestNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_REQUEST_NOT_FOUND,
            'Group request for customer "{{ id }}" is not found',
            ['id' => $id]
        );
    }

    /**
     * @param string[] $ids
     */
    public static function customersNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMERS_NOT_FOUND,
            'These customers "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)]
        );
    }

    public static function customerNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN,
            'Customer is not logged in.',
        );
    }

    public static function downloadFileNotFound(string $downloadId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND,
            'Line item download file with id "{{ downloadId }}" not found.',
            ['downloadId' => $downloadId]
        );
    }

    public static function customerIdsParameterIsMissing(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_IDS_PARAMETER_IS_MISSING,
            'Parameter "customerIds" is missing.',
        );
    }

    public static function unknownPaymentMethod(string $paymentMethodId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_CHANGE_PAYMENT_ERROR,
            'Change Payment to method {{ paymentMethodId }} not possible.',
            ['paymentMethodId' => $paymentMethodId]
        );
    }

    public static function productIdsParameterIsMissing(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PRODUCT_IDS_PARAMETER_IS_MISSING,
            'Parameter "productIds" is missing.',
        );
    }

    public static function addressNotFound(string $id): AddressNotFoundException
    {
        return new AddressNotFoundException($id);
    }

    public static function countryNotFound(string $countryId): HttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            'Country with id "{{ countryId }}" not found.',
            ['countryId' => $countryId]
        );
    }

    public static function badCredentials(): BadCredentialsException
    {
        return new BadCredentialsException();
    }

    public static function cannotDeleteActiveAddress(string $id): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_IS_ACTIVE,
            'Customer address with id "{{ addressId }}" is an active address and cannot be deleted.',
            ['addressId' => $id]
        );
    }

    public static function cannotDeleteDefaultAddress(string $id): CannotDeleteDefaultAddressException
    {
        return new CannotDeleteDefaultAddressException($id);
    }

    public static function customerAlreadyConfirmed(string $id): CustomerAlreadyConfirmedException
    {
        return new CustomerAlreadyConfirmedException($id);
    }

    public static function customerGroupRegistrationConfigurationNotFound(string $customerGroupId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND,
            'Customer group registration for id {{ customerGroupId }} not found.',
            ['customerGroupId' => $customerGroupId]
        );
    }

    public static function customerNotFoundByHash(string $hash): CustomerNotFoundByHashException
    {
        return new CustomerNotFoundByHashException($hash);
    }

    public static function customerNotFoundByIdException(string $id): CustomerNotFoundByIdException
    {
        return new CustomerNotFoundByIdException($id);
    }

    public static function customerNotFound(string $email): CustomerNotFoundException
    {
        return new CustomerNotFoundException($email);
    }

    public static function customerRecoveryHashExpired(string $hash): CustomerRecoveryHashExpiredException
    {
        return new CustomerRecoveryHashExpiredException($hash);
    }

    public static function customerWishlistNotActivated(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::WISHLIST_IS_NOT_ACTIVATED,
            'Wishlist is not activated!'
        );
    }

    public static function customerWishlistNotFound(): CustomerWishlistNotFoundException
    {
        return new CustomerWishlistNotFoundException();
    }

    public static function duplicateWishlistProduct(): DuplicateWishlistProductException
    {
        return new DuplicateWishlistProductException();
    }

    public static function legacyPasswordEncoderNotFound(string $encoder): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LEGACY_PASSWORD_ENCODER_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'encoder', 'field' => 'name', 'value' => $encoder]
        );
    }

    public static function noHashProvided(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NO_HASH_PROVIDED,
            'The given hash is empty.'
        );
    }

    public static function wishlistProductNotFound(string $productId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_PRODUCT_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'wishlist product', 'field' => 'id', 'value' => $productId]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed as it is unused
     */
    public static function inactiveCustomer(string $id): ShopwareHttpException
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Method "CustomerException::inactiveCustomer" will be removed as it is unused.');

        return self::customerOptinNotCompleted($id);
    }

    /**
     * @deprecated tag:v6.7.0 - Parameter $message will be removed as it is unused
     */
    public static function customerOptinNotCompleted(string $id, ?string $message = null): CustomerOptinNotCompletedException
    {
        if ($message !== null) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The parameter $message is unused and will be removed.');
        }

        return new CustomerOptinNotCompletedException($id);
    }

    public static function customerAuthThrottledException(int $waitTime, ?\Throwable $e = null): CustomerAuthThrottledException
    {
        return new CustomerAuthThrottledException(
            $waitTime,
            $e
        );
    }

    public static function guestAccountInvalidAuth(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_GUEST_AUTH_INVALID,
            'Guest account is not allowed to login'
        );
    }

    public static function passwordPoliciesUpdated(): PasswordPoliciesUpdatedException
    {
        return new PasswordPoliciesUpdatedException();
    }

    public static function invalidImitationToken(string $token): InvalidImitateCustomerTokenException
    {
        return new InvalidImitateCustomerTokenException($token);
    }
}
