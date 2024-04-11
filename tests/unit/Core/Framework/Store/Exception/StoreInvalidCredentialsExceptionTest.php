<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreInvalidCredentialsException::class)]
class StoreInvalidCredentialsExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_INVALID_CREDENTIALS',
            (new StoreInvalidCredentialsException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new StoreInvalidCredentialsException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Invalid credentials',
            (new StoreInvalidCredentialsException())->getMessage()
        );
    }
}
