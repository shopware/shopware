<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Country\CountryException
 */
#[Package('buyers-experience')]
class CountryExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testItThrowsException(ShopwareHttpException|CountryException $exception, int $statusCode, string $errorCode, string $message): void
    {
        try {
            throw $exception;
        } catch (ShopwareHttpException|CountryException $customerException) {
            $caughtException = $customerException;
        }

        static::assertEquals($statusCode, $caughtException->getStatusCode());
        static::assertEquals($errorCode, $caughtException->getErrorCode());
        static::assertEquals($message, $caughtException->getMessage());
    }

    /**
     * @return array<string, array{exception: ShopwareHttpException|CountryException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield CountryException::COUNTRY_NOT_FOUND => [
            'exception' => CountryException::countryNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CountryException::COUNTRY_NOT_FOUND,
            'message' => 'Country with id "id-1" not found.',
        ];

        yield CountryException::COUNTRY_STATE_NOT_FOUND => [
            'exception' => CountryException::countryStateNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CountryException::COUNTRY_STATE_NOT_FOUND,
            'message' => 'Country state with id "id-1" not found.',
        ];
    }
}
