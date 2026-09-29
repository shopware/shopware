<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserException::class)]
class UserExceptionTest extends TestCase
{
    public function testSalesChannelNotFound(): void
    {
        $exception = UserException::salesChannelNotFound();

        static::assertInstanceOf(UserException::class, $exception);
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(UserException::SALES_CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No sales channel found.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testMissingRequestParameter(): void
    {
        $exception = UserException::missingRequestParameter('email', 'user.email');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::MISSING_REQUEST_PARAMETER_CODE, $exception->getErrorCode());
        static::assertSame('Parameter "email" is missing.', $exception->getMessage());
        static::assertSame([
            'parameterName' => 'email',
            'path' => 'user.email',
        ], $exception->getParameters());
    }
}
