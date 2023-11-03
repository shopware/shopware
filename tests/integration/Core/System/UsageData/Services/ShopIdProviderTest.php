<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;

/**
 * @internal
 */
#[Package('merchant-services')]
class ShopIdProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private AbstractKeyValueStorage $configService;

    protected function setUp(): void
    {
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->configService = $this->getContainer()->get(AbstractKeyValueStorage::class);
    }

    public function testCreatesANewShopIdIfNoneIsGiven(): void
    {
        $this->configService->remove(ShopIdProvider::USAGE_DATA_SHOP_ID_CONFIG_KEY);

        $shopId = $this->shopIdProvider->getShopId();

        static::assertTrue(Uuid::isValid($shopId));
    }

    public function testDoesNotGenerateANewShopIdIfAlreadyGiven(): void
    {
        $expectedShopId = Uuid::randomHex();
        $this->configService->set(ShopIdProvider::USAGE_DATA_SHOP_ID_CONFIG_KEY, $expectedShopId);

        $shopId = $this->shopIdProvider->getShopId();

        static::assertEquals($expectedShopId, $shopId);
    }
}
