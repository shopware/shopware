<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerException::class)]
class CustomerExceptionTest extends TestCase
{
    public function testCustomerGroupNotFound(): void
    {
        $exception = CustomerException::customerGroupNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_GROUP_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find customer group with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'customer group', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testGroupRequestNotFound(): void
    {
        $exception = CustomerException::groupRequestNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_GROUP_REQUEST_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Group request for customer "id-1" is not found', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testCustomersNotFound(): void
    {
        $exception = CustomerException::customersNotFound(['id-1', 'id-2']);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMERS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('These customers "id-1, id-2" are not found', $exception->getMessage());
        static::assertSame(['ids' => 'id-1, id-2'], $exception->getParameters());
    }

    public function testCustomerNotLoggedIn(): void
    {
        $exception = CustomerException::customerNotLoggedIn();

        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_NOT_LOGGED_IN, $exception->getErrorCode());
        static::assertSame('Customer is not logged in.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testDownloadFileNotFound(): void
    {
        $exception = CustomerException::downloadFileNotFound('id-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Line item download file with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['downloadId' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerIdsParameterIsMissing(): void
    {
        $exception = CustomerException::customerIdsParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_IDS_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "customerIds" is missing.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testUnknownPaymentMethod(): void
    {
        $exception = CustomerException::unknownPaymentMethod('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_CHANGE_PAYMENT_ERROR, $exception->getErrorCode());
        static::assertSame('Change Payment to method id-1 not possible.', $exception->getMessage());
        static::assertSame(['paymentMethodId' => 'id-1'], $exception->getParameters());
    }

    public function testProductIdsParameterIsMissing(): void
    {
        $exception = CustomerException::productIdsParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::PRODUCT_IDS_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "productIds" is missing.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testAddressNotFound(): void
    {
        $exception = CustomerException::addressNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_ADDRESS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Customer address with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testBadCredentials(): void
    {
        $exception = CustomerException::badCredentials();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_AUTH_BAD_CREDENTIALS, $exception->getErrorCode());
        static::assertSame('Invalid username and/or password.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testCannotDeleteActiveAddress(): void
    {
        $exception = CustomerException::cannotDeleteActiveAddress('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_ADDRESS_IS_ACTIVE, $exception->getErrorCode());
        static::assertSame('Customer address with id "id-1" is an active address and cannot be deleted.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testCannotDeleteDefaultAddress(): void
    {
        $exception = CustomerException::cannotDeleteDefaultAddress('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_ADDRESS_IS_DEFAULT, $exception->getErrorCode());
        static::assertSame('Customer address with id "id-1" is a default address and cannot be deleted.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerAlreadyConfirmed(): void
    {
        $exception = CustomerException::customerAlreadyConfirmed('id-1');

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_IS_ALREADY_CONFIRMED, $exception->getErrorCode());
        static::assertSame('The customer with the id "id-1" is already confirmed.', $exception->getMessage());
        static::assertSame(['customerId' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerGroupRegistrationConfigurationNotFound(): void
    {
        $exception = CustomerException::customerGroupRegistrationConfigurationNotFound('id-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Customer group registration for id id-1 not found.', $exception->getMessage());
        static::assertSame(['customerGroupId' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerNotFoundByHash(): void
    {
        $exception = CustomerException::customerNotFoundByHash('e9c8985e0b0f8ec20a16ac9ffd0m');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_NOT_FOUND_BY_HASH, $exception->getErrorCode());
        static::assertSame('No matching customer for the hash "e9c8985e0b0f8ec20a16ac9ffd0m" was found.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testCustomerNotFoundByIdException(): void
    {
        $exception = CustomerException::customerNotFoundByIdException('id-1');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_NOT_FOUND_BY_ID, $exception->getErrorCode());
        static::assertSame('No matching customer for the id "id-1" was found.', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerNotFound(): void
    {
        $exception = CustomerException::customerNotFound('abc@com');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No matching customer for the email "abc@com" was found.', $exception->getMessage());
        static::assertSame(['email' => 'abc@com'], $exception->getParameters());
    }

    public function testCustomerRecoveryHashExpired(): void
    {
        $exception = CustomerException::customerRecoveryHashExpired('e9c8985e0b0f8ec20a16ac9ffd0m');

        static::assertSame(Response::HTTP_GONE, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_RECOVERY_HASH_EXPIRED, $exception->getErrorCode());
        static::assertSame('The hash "e9c8985e0b0f8ec20a16ac9ffd0m" is expired.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testCustomerWishlistNotActivated(): void
    {
        $exception = CustomerException::customerWishlistNotActivated();

        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(CustomerException::WISHLIST_IS_NOT_ACTIVATED, $exception->getErrorCode());
        static::assertSame('Wishlist is not activated!', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testCustomerWishlistNotFound(): void
    {
        $exception = CustomerException::customerWishlistNotFound();

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::WISHLIST_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Wishlist for this customer was not found.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testDuplicateWishlistProduct(): void
    {
        $exception = CustomerException::duplicateWishlistProduct();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::DUPLICATE_WISHLIST_PRODUCT, $exception->getErrorCode());
        static::assertSame('Product already added in wishlist', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testLegacyPasswordEncoderNotFound(): void
    {
        $exception = CustomerException::legacyPasswordEncoderNotFound('encoder');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::LEGACY_PASSWORD_ENCODER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find encoder with name "encoder"', $exception->getMessage());
        static::assertSame(['entity' => 'encoder', 'field' => 'name', 'value' => 'encoder'], $exception->getParameters());
    }

    public function testNoHashProvided(): void
    {
        $exception = CustomerException::noHashProvided();

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::NO_HASH_PROVIDED, $exception->getErrorCode());
        static::assertSame('The given hash is empty.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testWishlistProductNotFound(): void
    {
        $exception = CustomerException::wishlistProductNotFound('id-1');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(CustomerException::WISHLIST_PRODUCT_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find wishlist product with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'wishlist product', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testCustomerOptinNotCompleted(): void
    {
        $exception = CustomerException::customerOptinNotCompleted('id-1');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED, $exception->getErrorCode());
        static::assertSame('The customer with the id "id-1" has not completed the opt-in.', $exception->getMessage());
        static::assertSame(['customerId' => 'id-1'], $exception->getParameters());
        static::assertSame('account.doubleOptinAccountAlert', $exception->getSnippetKey());
    }

    public function testCustomerAuthThrottledException(): void
    {
        $exception = CustomerException::customerAuthThrottledException(100);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_AUTH_THROTTLED, $exception->getErrorCode());
        static::assertSame('Customer auth throttled for 100 seconds.', $exception->getMessage());
        static::assertSame(100, $exception->getWaitTime());
    }

    public function testGuestAccountInvalidAuth(): void
    {
        $exception = CustomerException::guestAccountInvalidAuth();

        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(CustomerException::CUSTOMER_GUEST_AUTH_INVALID, $exception->getErrorCode());
        static::assertSame('Guest account is not allowed to login', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testCountryNotFound(): void
    {
        $exception = CustomerException::countryNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CustomerException::COUNTRY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Country with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['countryId' => 'id-1'], $exception->getParameters());
    }
}
