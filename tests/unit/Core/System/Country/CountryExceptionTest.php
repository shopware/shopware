<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CountryException::class)]
class CountryExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
    public function testItThrowsException(ShopwareHttpException|CountryException $exception, int $statusCode, string $errorCode, string $message): void
    {
        try {
            throw $exception;
        } catch (ShopwareHttpException|CountryException $customerException) {
            $caughtException = $customerException;
        }

        static::assertSame($statusCode, $caughtException->getStatusCode());
        static::assertSame($errorCode, $caughtException->getErrorCode());
        static::assertSame($message, $caughtException->getMessage());
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
            'message' => 'Could not find country with id "id-1"',
        ];

        yield CountryException::COUNTRY_STATE_NOT_FOUND => [
            'exception' => CountryException::countryStateNotFound('id-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CountryException::COUNTRY_STATE_NOT_FOUND,
            'message' => 'Could not find country state with id "id-1"',
        ];
    }
}
