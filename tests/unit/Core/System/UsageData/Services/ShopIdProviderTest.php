<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Services\ShopIdProvider
 */
class ShopIdProviderTest extends TestCase
{
    public function testReturnsShopIdWithoutAnyException(): void
    {
        $appShopIdProvider = $this->createMock(\Shopware\Core\Framework\App\ShopId\ShopIdProvider::class);
        $appShopIdProvider->expects(static::once())
            ->method('getShopId')
            ->willReturn('shopId');

        $providerToTest = new ShopIdProvider($appShopIdProvider);

        static::assertSame('shopId', $providerToTest->getShopId());
    }

    public function testReturnsShopIdOnException(): void
    {
        $exception = new AppUrlChangeDetectedException('oldUrl', 'currentUrl', 'shopId');

        $appShopIdProvider = $this->createMock(\Shopware\Core\Framework\App\ShopId\ShopIdProvider::class);
        $appShopIdProvider->expects(static::once())
            ->method('getShopId')
            ->willThrowException($exception);

        $providerToTest = new ShopIdProvider($appShopIdProvider);

        static::assertSame('shopId', $providerToTest->getShopId());
    }
}
