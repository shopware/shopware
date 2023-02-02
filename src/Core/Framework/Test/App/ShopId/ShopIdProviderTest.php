<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ShopId;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ShopIdProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use EnvTestBehaviour;
    use AppSystemTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private SystemConfigService $systemConfigService;

    public function setUp(): void
    {
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testGetShopIdWithoutStoredShopId(): void
    {
        $shopId = $this->shopIdProvider->getShopId();

        static::assertEquals([
            'app_url' => $_SERVER['APP_URL'],
            'value' => $shopId,
        ], $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
    }

    public function testGetShopIdReturnsSameIdOnMultipleCalls(): void
    {
        $firstShopId = $this->shopIdProvider->getShopId();
        $secondShopId = $this->shopIdProvider->getShopId();

        static::assertEquals($firstShopId, $secondShopId);

        static::assertEquals([
            'app_url' => $_SERVER['APP_URL'],
            'value' => $firstShopId,
        ], $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
    }

    public function testGetShopIdThrowsIfAppUrlIsChangedAndAppsArePresent(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $this->shopIdProvider->getShopId();

        $this->setEnvVars([
            'APP_URL' => 'http://test.com',
        ]);

        try {
            $this->shopIdProvider->getShopId();
            static::fail('expected AppUrlChangeDetectedException was not thrown.');
        } catch (AppUrlChangeDetectedException $e) {
            // exception is expected
        }
    }

    public function testGetShopIdUpdatesItselfIfAppUrlIsChangedAndNoAppsArePresent(): void
    {
        $firstShopId = $this->shopIdProvider->getShopId();

        $this->setEnvVars([
            'APP_URL' => 'http://test.com',
        ]);

        $secondShopId = $this->shopIdProvider->getShopId();

        static::assertEquals([
            'app_url' => 'http://test.com',
            'value' => $firstShopId,
        ], $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));

        static::assertEquals($firstShopId, $secondShopId);
    }

    public function testItRemovesTheAppUrlChangedMarkerIfOutdated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $this->shopIdProvider->getShopId();

        $this->setEnvVars([
            'APP_URL' => 'http://test.com',
        ]);

        try {
            $this->shopIdProvider->getShopId();
            static::fail('expected AppUrlChangeDetectedException was not thrown.');
        } catch (AppUrlChangeDetectedException $e) {
            // exception is expected
        }

        $this->resetEnvVars();

        $this->shopIdProvider->getShopId();
    }
}
