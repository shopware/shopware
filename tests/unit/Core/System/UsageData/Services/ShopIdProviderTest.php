<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Services\ShopIdProvider
 */
#[Package('merchant-services')]
class ShopIdProviderTest extends TestCase
{
    public function testCreatesANewShopIdIfNoneIsGiven(): void
    {
        $shopId = Uuid::randomHex();

        $shopIdProvider = new ShopIdProvider(new ArrayKeyValueStorage());
        $shopId = $shopIdProvider->getShopId();

        static::assertTrue(Uuid::isValid($shopId));
    }

    public function testDoesNotGenerateANewShopIdIfAlreadyGiven(): void
    {
        $expectedShopId = 'this-is-a-unique-shop-id';

        $shopIdProvider = new ShopIdProvider(new ArrayKeyValueStorage([
            ShopIdProvider::USAGE_DATA_SHOP_ID_CONFIG_KEY => $expectedShopId,
        ]));
        $shopId = $shopIdProvider->getShopId();

        static::assertEquals($expectedShopId, $shopId);
    }
}
