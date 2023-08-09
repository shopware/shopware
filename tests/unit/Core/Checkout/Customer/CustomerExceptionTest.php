<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\CustomerException
 */
#[Package('customer-order')]
class CustomerExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testItThrowsException(ShopwareHttpException|CustomerException $exception, int $statusCode, string $errorCode, string $message): void
    {
        try {
            throw $exception;
        } catch (ShopwareHttpException|CustomerException $customerException) {
            $caughtException = $customerException;
        }

        static::assertEquals($statusCode, $caughtException->getStatusCode());
        static::assertEquals($errorCode, $caughtException->getErrorCode());
        static::assertEquals($message, $caughtException->getMessage());
    }

    /**
     * @return array<string, array{exception: ShopwareHttpException|CustomerException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield CustomerException::CUSTOMER_GROUP_NOT_FOUND => [
            'exception' => CustomerException::customerGroupNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_GROUP_NOT_FOUND,
            'message' => 'Customer group with id "id-1" not found',
        ];

        yield CustomerException::CUSTOMER_GROUP_REQUEST_NOT_FOUND => [
            'exception' => CustomerException::groupRequestNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_GROUP_REQUEST_NOT_FOUND,
            'message' => 'Group request for customer "id-1" is not found',
        ];

        yield CustomerException::CUSTOMERS_NOT_FOUND => [
            'exception' => CustomerException::customersNotFound(['id-1', 'id-2']),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMERS_NOT_FOUND,
            'message' => 'These customers "id-1, id-2" are not found',
        ];

        yield CustomerException::CUSTOMER_NOT_LOGGED_IN => [
            'exception' => CustomerException::customerNotLoggedIn(),
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => CustomerException::CUSTOMER_NOT_LOGGED_IN,
            'message' => 'Customer is not logged in.',
        ];

        yield CustomerException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND => [
            'exception' => CustomerException::downloadFileNotFound('id-1'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND,
            'message' => 'Line item download file with id "id-1" not found.',
        ];

        yield CustomerException::CUSTOMER_IDS_PARAMETER_IS_MISSING => [
            'exception' => CustomerException::customerIdsParameterIsMissing(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_IDS_PARAMETER_IS_MISSING,
            'message' => 'Parameter "customerIds" is missing.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_NOT_FOUND => [
            'exception' => CustomerException::addressNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_NOT_FOUND,
            'message' => 'Customer address with id "id-1" not found.',
        ];

        yield CustomerException::CUSTOMER_AUTH_BAD_CREDENTIALS => [
            'exception' => CustomerException::badCredentials(),
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_AUTH_BAD_CREDENTIALS,
            'message' => 'Invalid username and/or password.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_IS_ACTIVE => [
            'exception' => CustomerException::cannotDeleteActiveAddress('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_IS_ACTIVE,
            'message' => 'Customer address with id "id-1" is an active address and cannot be deleted.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_IS_DEFAULT => [
            'exception' => CustomerException::cannotDeleteDefaultAddress('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_IS_DEFAULT,
            'message' => 'Customer address with id "id-1" is a default address and cannot be deleted.',
        ];

        yield CustomerException::CUSTOMER_IS_ALREADY_CONFIRMED => [
            'exception' => CustomerException::customerAlreadyConfirmed('id-1'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => CustomerException::CUSTOMER_IS_ALREADY_CONFIRMED,
            'message' => 'The customer with the id "id-1" is already confirmed.',
        ];

        yield CustomerException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND => [
            'exception' => CustomerException::customerGroupRegistrationConfigurationNotFound('id-1'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND,
            'message' => 'Customer group registration for id id-1 not found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND_BY_HASH => [
            'exception' => CustomerException::customerNotFoundByHash('id-1'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND_BY_HASH,
            'message' => 'No matching customer for the hash "id-1" was found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND_BY_ID => [
            'exception' => CustomerException::customerNotFoundByIdException('id-1'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND_BY_ID,
            'message' => 'No matching customer for the id "id-1" was found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND => [
            'exception' => CustomerException::customerNotFound('abc@com'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND,
            'message' => 'No matching customer for the email "abc@com" was found.',
        ];

        yield CustomerException::CUSTOMER_RECOVERY_HASH_EXPIRED => [
            'exception' => CustomerException::customerRecoveryHashExpired('abc@com'),
            'statusCode' => Response::HTTP_GONE,
            'errorCode' => CustomerException::CUSTOMER_RECOVERY_HASH_EXPIRED,
            'message' => 'The hash "abc@com" is expired.',
        ];

        yield CustomerException::WISHLIST_IS_NOT_ACTIVATED => [
            'exception' => CustomerException::customerWishlistNotActivated(),
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => CustomerException::WISHLIST_IS_NOT_ACTIVATED,
            'message' => 'Wishlist is not activated!',
        ];

        yield CustomerException::WISHLIST_NOT_FOUND => [
            'exception' => CustomerException::customerWishlistNotFound(),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::WISHLIST_NOT_FOUND,
            'message' => 'Wishlist for this customer was not found.',
        ];

        yield CustomerException::DUPLICATE_WISHLIST_PRODUCT => [
            'exception' => CustomerException::duplicateWishlistProduct(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::DUPLICATE_WISHLIST_PRODUCT,
            'message' => 'Product already added in wishlist',
        ];

        yield CustomerException::LEGACY_PASSWORD_ENCODER_NOT_FOUND => [
            'exception' => CustomerException::legacyPasswordEncoderNotFound('encoder'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::LEGACY_PASSWORD_ENCODER_NOT_FOUND,
            'message' => 'Encoder with name "encoder" not found.',
        ];

        yield CustomerException::NO_HASH_PROVIDED => [
            'exception' => CustomerException::noHashProvided(),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::NO_HASH_PROVIDED,
            'message' => 'The given hash is empty.',
        ];

        yield CustomerException::WISHLIST_PRODUCT_NOT_FOUND => [
            'exception' => CustomerException::wishlistProductNotFound('id-1'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::WISHLIST_PRODUCT_NOT_FOUND,
            'message' => 'Wishlist product with id id-1 not found',
        ];

        if (!Feature::isActive('v6.6.0.0')) {
            yield CustomerException::CUSTOMER_IS_INACTIVE => [
                'exception' => CustomerException::inactiveCustomer('id-1'),
                'statusCode' => Response::HTTP_UNAUTHORIZED,
                'errorCode' => CustomerException::CUSTOMER_IS_INACTIVE,
                'message' => 'The customer with the id "id-1" is inactive.',
            ];
        } else {
            yield CustomerException::CUSTOMER_IS_INACTIVE => [
                'exception' => CustomerException::inactiveCustomer('id-1'),
                'statusCode' => Response::HTTP_UNAUTHORIZED,
                'errorCode' => CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED,
                'message' => 'The customer with the id "id-1" has not completed the opt-in.',
            ];
        }

        yield CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED => [
            'exception' => CustomerException::customerOptinNotCompleted('id-1'),
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED,
            'message' => 'The customer with the id "id-1" has not completed the opt-in.',
        ];

        yield CustomerException::CUSTOMER_AUTH_THROTTLED => [
            'exception' => CustomerException::customerAuthThrottledException(100),
            'statusCode' => Response::HTTP_TOO_MANY_REQUESTS,
            'errorCode' => CustomerException::CUSTOMER_AUTH_THROTTLED,
            'message' => 'Customer auth throttled for 100 seconds.',
        ];

        if (!Feature::isActive('v6.6.0.0')) {
            yield CustomerException::COUNTRY_NOT_FOUND => [
                'exception' => CustomerException::countryNotFound('100'),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => CountryException::COUNTRY_NOT_FOUND,
                'message' => 'Country with id "100" not found.',
            ];
        } else {
            yield CustomerException::COUNTRY_NOT_FOUND => [
                'exception' => CustomerException::countryNotFound('100'),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => CustomerException::COUNTRY_NOT_FOUND,
                'message' => 'Country with id "100" not found.',
            ];
        }
    }
}
