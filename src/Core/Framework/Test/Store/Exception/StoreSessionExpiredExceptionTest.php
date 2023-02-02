<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class StoreSessionExpiredExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_SESSION_EXPIRED',
            (new StoreSessionExpiredException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new StoreSessionExpiredException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store session has expired',
            (new StoreSessionExpiredException())->getMessage()
        );
    }
}
