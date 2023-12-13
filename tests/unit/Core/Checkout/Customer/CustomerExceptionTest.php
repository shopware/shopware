<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param callable(mixed...): \Throwable $callback
     * @param mixed[] $args
     */
    #[DataProvider('exceptionDataProvider')]
    public function testItThrowsException(callable $callback, array $args, int $statusCode, string $errorCode, string $message): void
    {
        // phpunit data-providers collect data before the test is executed
        // therefore coverage is not possible, other than we create the exception inside the test body
        $exception = $callback(...$args);

        try {
            throw $exception;
        } catch (CustomerException $e) {
            static::assertSame($statusCode, $e->getStatusCode());
            static::assertSame($errorCode, $e->getErrorCode());
            static::assertSame($message, $e->getMessage());
        } catch (\Throwable $e) {
            static::fail(\sprintf('Exception with message "%s" of type %s has not expected type %s', $e->getMessage(), $e::class, CustomerException::class));
        }
    }

    public static function exceptionDataProvider(): \Generator
    {
        yield CustomerException::CUSTOMER_GROUP_NOT_FOUND => [
            'callback' => [CustomerException::class, 'customerGroupNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_GROUP_NOT_FOUND,
            'message' => 'Could not find customer group with id "id-1"',
        ];

        yield CustomerException::CUSTOMER_GROUP_REQUEST_NOT_FOUND => [
            'callback' => [CustomerException::class, 'groupRequestNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_GROUP_REQUEST_NOT_FOUND,
            'message' => 'Group request for customer "id-1" is not found',
        ];

        yield CustomerException::CUSTOMERS_NOT_FOUND => [
            'callback' => [CustomerException::class, 'customersNotFound'],
            'args' => [['id-1', 'id-2']],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMERS_NOT_FOUND,
            'message' => 'These customers "id-1, id-2" are not found',
        ];

        yield CustomerException::CUSTOMER_NOT_LOGGED_IN => [
            'callback' => [CustomerException::class, 'customerNotLoggedIn'],
            'args' => [],
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => CustomerException::CUSTOMER_NOT_LOGGED_IN,
            'message' => 'Customer is not logged in.',
        ];

        yield CustomerException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND => [
            'callback' => [CustomerException::class, 'downloadFileNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND,
            'message' => 'Line item download file with id "id-1" not found.',
        ];

        yield CustomerException::CUSTOMER_IDS_PARAMETER_IS_MISSING => [
            'callback' => [CustomerException::class, 'customerIdsParameterIsMissing'],
            'args' => [],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_IDS_PARAMETER_IS_MISSING,
            'message' => 'Parameter "customerIds" is missing.',
        ];

        yield CustomerException::CUSTOMER_CHANGE_PAYMENT_ERROR => [
            'callback' => [CustomerException::class, 'unknownPaymentMethod'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_CHANGE_PAYMENT_ERROR,
            'message' => 'Change Payment to method id-1 not possible.',
        ];

        yield CustomerException::PRODUCT_IDS_PARAMETER_IS_MISSING => [
            'callback' => [CustomerException::class, 'productIdsParameterIsMissing'],
            'args' => [],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::PRODUCT_IDS_PARAMETER_IS_MISSING,
            'message' => 'Parameter "productIds" is missing.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_NOT_FOUND => [
            'callback' => [CustomerException::class, 'addressNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_NOT_FOUND,
            'message' => 'Customer address with id "id-1" not found.',
        ];

        yield CustomerException::CUSTOMER_AUTH_BAD_CREDENTIALS => [
            'callback' => [CustomerException::class, 'badCredentials'],
            'args' => [],
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_AUTH_BAD_CREDENTIALS,
            'message' => 'Invalid username and/or password.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_IS_ACTIVE => [
            'callback' => [CustomerException::class, 'cannotDeleteActiveAddress'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_IS_ACTIVE,
            'message' => 'Customer address with id "id-1" is an active address and cannot be deleted.',
        ];

        yield CustomerException::CUSTOMER_ADDRESS_IS_DEFAULT => [
            'callback' => [CustomerException::class, 'cannotDeleteDefaultAddress'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::CUSTOMER_ADDRESS_IS_DEFAULT,
            'message' => 'Customer address with id "id-1" is a default address and cannot be deleted.',
        ];

        yield CustomerException::CUSTOMER_IS_ALREADY_CONFIRMED => [
            'callback' => [CustomerException::class, 'customerAlreadyConfirmed'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => CustomerException::CUSTOMER_IS_ALREADY_CONFIRMED,
            'message' => 'The customer with the id "id-1" is already confirmed.',
        ];

        yield CustomerException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND => [
            'callback' => [CustomerException::class, 'customerGroupRegistrationConfigurationNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND,
            'message' => 'Customer group registration for id id-1 not found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND_BY_HASH => [
            'callback' => [CustomerException::class, 'customerNotFoundByHash'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND_BY_HASH,
            'message' => 'No matching customer for the hash "id-1" was found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND_BY_ID => [
            'callback' => [CustomerException::class, 'customerNotFoundByIdException'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND_BY_ID,
            'message' => 'No matching customer for the id "id-1" was found.',
        ];

        yield CustomerException::CUSTOMER_NOT_FOUND => [
            'callback' => [CustomerException::class, 'customerNotFound'],
            'args' => ['abc@com'],
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_NOT_FOUND,
            'message' => 'No matching customer for the email "abc@com" was found.',
        ];

        yield CustomerException::CUSTOMER_RECOVERY_HASH_EXPIRED => [
            'callback' => [CustomerException::class, 'customerRecoveryHashExpired'],
            'args' => ['abc@com'],
            'statusCode' => Response::HTTP_GONE,
            'errorCode' => CustomerException::CUSTOMER_RECOVERY_HASH_EXPIRED,
            'message' => 'The hash "abc@com" is expired.',
        ];

        yield CustomerException::WISHLIST_IS_NOT_ACTIVATED => [
            'callback' => [CustomerException::class, 'customerWishlistNotActivated'],
            'args' => [],
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => CustomerException::WISHLIST_IS_NOT_ACTIVATED,
            'message' => 'Wishlist is not activated!',
        ];

        yield CustomerException::WISHLIST_NOT_FOUND => [
            'callback' => [CustomerException::class, 'customerWishlistNotFound'],
            'args' => [],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::WISHLIST_NOT_FOUND,
            'message' => 'Wishlist for this customer was not found.',
        ];

        yield CustomerException::DUPLICATE_WISHLIST_PRODUCT => [
            'callback' => [CustomerException::class, 'duplicateWishlistProduct'],
            'args' => [],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::DUPLICATE_WISHLIST_PRODUCT,
            'message' => 'Product already added in wishlist',
        ];

        yield CustomerException::LEGACY_PASSWORD_ENCODER_NOT_FOUND => [
            'callback' => [CustomerException::class, 'legacyPasswordEncoderNotFound'],
            'args' => ['encoder'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::LEGACY_PASSWORD_ENCODER_NOT_FOUND,
            'message' => 'Could not find encoder with name "encoder"',
        ];

        yield CustomerException::NO_HASH_PROVIDED => [
            'callback' => [CustomerException::class, 'noHashProvided'],
            'args' => [],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::NO_HASH_PROVIDED,
            'message' => 'The given hash is empty.',
        ];

        yield CustomerException::WISHLIST_PRODUCT_NOT_FOUND => [
            'callback' => [CustomerException::class, 'wishlistProductNotFound'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => CustomerException::WISHLIST_PRODUCT_NOT_FOUND,
            'message' => 'Could not find wishlist product with id "id-1"',
        ];

        yield CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED => [
            'callback' => [CustomerException::class, 'customerOptinNotCompleted'],
            'args' => ['id-1'],
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => CustomerException::CUSTOMER_OPTIN_NOT_COMPLETED,
            'message' => 'The customer with the id "id-1" has not completed the opt-in.',
        ];

        yield CustomerException::CUSTOMER_AUTH_THROTTLED => [
            'callback' => [CustomerException::class, 'customerAuthThrottledException'],
            'args' => [100],
            'statusCode' => Response::HTTP_TOO_MANY_REQUESTS,
            'errorCode' => CustomerException::CUSTOMER_AUTH_THROTTLED,
            'message' => 'Customer auth throttled for 100 seconds.',
        ];

        yield CustomerException::CUSTOMER_GUEST_AUTH_INVALID => [
            'callback' => [CustomerException::class, 'guestAccountInvalidAuth'],
            'args' => [],
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => CustomerException::CUSTOMER_GUEST_AUTH_INVALID,
            'message' => 'Guest account is not allowed to login',
        ];

        yield CustomerException::COUNTRY_NOT_FOUND => [
            'callback' => [CustomerException::class, 'countryNotFound'],
            'args' => ['100'],
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CustomerException::COUNTRY_NOT_FOUND,
            'message' => 'Country with id "100" not found.',
        ];
    }
}
