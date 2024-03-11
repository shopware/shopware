<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(ShopIdProvider::class)]
class ShopIdProviderTest extends TestCase
{
    public function testReturnsShopIdFromSystemConfig(): void
    {
        $appShopIdProvider = $this->createMock(\Shopware\Core\Framework\App\ShopId\ShopIdProvider::class);
        $appShopIdProvider->expects(static::never())
            ->method('getShopId');

        $providerToTest = new ShopIdProvider(
            $appShopIdProvider,
            new StaticSystemConfigService([
                \Shopware\Core\Framework\App\ShopId\ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                    'value' => 'shop-id-from-system-config',
                    'app_url' => 'appUrl',
                ],
            ])
        );

        static::assertSame('shop-id-from-system-config', $providerToTest->getShopId());
    }

    public function testReturnsShopIdFromInner(): void
    {
        $appShopIdProvider = $this->createMock(\Shopware\Core\Framework\App\ShopId\ShopIdProvider::class);
        $appShopIdProvider->expects(static::once())
            ->method('getShopId')
            ->willReturn('shopId');

        $providerToTest = new ShopIdProvider(
            $appShopIdProvider,
            new StaticSystemConfigService()
        );

        static::assertSame('shopId', $providerToTest->getShopId());
    }
}
