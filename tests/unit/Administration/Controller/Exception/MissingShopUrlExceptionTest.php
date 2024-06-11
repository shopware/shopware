<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\Exception\MissingShopUrlException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(MissingShopUrlException::class)]
class MissingShopUrlExceptionTest extends TestCase
{
    public function testMissingShopUrlException(): void
    {
        $exception = new MissingShopUrlException();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('ADMINISTRATION__MISSING_SHOP_URL', $exception->getErrorCode());
        static::assertSame('Failed to retrieve the shop url.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }
}
