<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\Country\CountryException;
use Shopware\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(CountryException::class)]
class UserExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
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
        yield UserException::SALES_CHANNEL_NOT_FOUND => [
            'exception' => UserException::salesChannelNotFound(),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => UserException::SALES_CHANNEL_NOT_FOUND,
            'message' => 'No sales channel found.',
        ];
    }
}
