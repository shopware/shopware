<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppUrlVerificationFailedException;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AppUrlVerificationFailedException::class)]
#[Package('framework')]
class AppUrlVerificationFailedExceptionTest extends TestCase
{
    public function testException(): void
    {
        $shopId = ShopId::v2('123456789');
        $e = new AppUrlVerificationFailedException($shopId, 'https://www.example.com');

        static::assertSame(500, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_URL_FAILED_VERIFICATION', $e->getErrorCode());
        static::assertSame('App url https://www.example.com failed verification', $e->getMessage());
        static::assertSame($shopId, $e->getShopId());
    }
}
