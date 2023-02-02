<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ShopSecretInvalidException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ShopSecretInvalidExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_SHOP_SECRET_INVALID',
            (new ShopSecretInvalidException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new ShopSecretInvalidException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store shop secret is invalid',
            (new ShopSecretInvalidException())->getMessage()
        );
    }
}
