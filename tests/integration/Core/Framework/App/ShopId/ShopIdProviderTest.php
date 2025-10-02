<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ShopId;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 *
 * @phpstan-import-type ShopIdV1Config from ShopId
 * @phpstan-import-type ShopIdV2Config from ShopId
 */
#[Package('framework')]
class ShopIdProviderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testGeneratesNewShopIdV2IfNoShopIdPresentInSystemConfig(): void
    {
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2));

        $shopId = $this->shopIdProvider->getShopId();
        $shopIdConfig = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);

        static::assertIsArray($shopIdConfig);

        static::assertArrayHasKey('id', $shopIdConfig);
        static::assertSame($shopId, $shopIdConfig['id']);

        static::assertArrayHasKey('version', $shopIdConfig);
        static::assertSame(2, $shopIdConfig['version']);

        static::assertArrayHasKey('fingerprints', $shopIdConfig);
        static::assertArrayHasKey(AppUrl::IDENTIFIER, $shopIdConfig['fingerprints']);
        static::assertSame($_SERVER['APP_URL'], $shopIdConfig['fingerprints'][AppUrl::IDENTIFIER]);
    }

    public function testUpgradesShopIdToV2IfShopIdV1PresentInSystemConfig(): void
    {
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2));

        $this->systemConfigService->set(
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY,
            $this->createShopIdV1Config('1234567890', $_SERVER['APP_URL'])
        );

        $this->setEnvVars(['APP_URL' => $newAppUrl = 'https://new.url']);

        $shopId = $this->shopIdProvider->getShopId();
        $shopIdV2Config = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);

        static::assertIsArray($shopIdV2Config);

        static::assertArrayHasKey('id', $shopIdV2Config);
        static::assertSame($shopId, $shopIdV2Config['id']);

        static::assertArrayHasKey('version', $shopIdV2Config);
        static::assertSame(2, $shopIdV2Config['version']);

        static::assertArrayHasKey('fingerprints', $shopIdV2Config);
        static::assertArrayHasKey(AppUrl::IDENTIFIER, $shopIdV2Config['fingerprints']);
        static::assertSame($newAppUrl, $shopIdV2Config['fingerprints'][AppUrl::IDENTIFIER]);
    }

    public function testThrowsIfFingerprintComparisonDoesNotMatch(): void
    {
        $oldAppUrl = EnvironmentHelper::getVariable('APP_URL');

        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $this->setEnvVars(['APP_URL' => $newAppUrl = 'https://new.url']);

        try {
            $this->shopIdProvider->reset();
            $this->shopIdProvider->getShopId();

            static::fail(\sprintf('Expected %s to be thrown', ShopIdChangeSuggestedException::class));
        } catch (ShopIdChangeSuggestedException $e) {
            static::assertSame($oldAppUrl, $e->comparisonResult->getMismatchingFingerprint(AppUrl::IDENTIFIER)?->storedStamp);
            static::assertSame($newAppUrl, $e->comparisonResult->getMismatchingFingerprint(AppUrl::IDENTIFIER)?->expectedStamp);
        }
    }

    public function testUpdatesShopIdIfFingerprintsHaveChangedButHasNoAppsRegisteredAtAppServers(): void
    {
        /** @var string $appUrlBeforeUpdate */
        $appUrlBeforeUpdate = EnvironmentHelper::getVariable('APP_URL');
        $shopIdBeforeUpdate = $this->shopIdProvider->getShopId();
        $shopIdConfigBeforeUpdate = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);
        static::assertSame($appUrlBeforeUpdate, $shopIdConfigBeforeUpdate['fingerprints'][AppUrl::IDENTIFIER] ?? null);

        $this->shopIdProvider->reset();

        $this->setEnvVars(['APP_URL' => $newAppUrl = 'https://new.url']);
        $shopIdAfterUpdate = $this->shopIdProvider->getShopId();
        static::assertSame($shopIdBeforeUpdate, $shopIdAfterUpdate);
        $shopIdConfigAfterUpdate = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);
        static::assertSame($newAppUrl, $shopIdConfigAfterUpdate['fingerprints'][AppUrl::IDENTIFIER] ?? null);
    }

    public function testDeletesShopIdConfigV1AndShopIdConfigV2(): void
    {
        $this->systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, ['value' => '1234567890', 'app_url' => 'https://foo.bar']);
        $this->systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, ['value' => '1234567890', 'version' => 2, 'fingerprints' => ['app_url' => 'https://foo.bar']]);

        static::assertIsArray($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
        static::assertIsArray($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2));

        $this->shopIdProvider->deleteShopId();

        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2));
    }

    /**
     * @return ShopIdV1Config
     */
    private function createShopIdV1Config(string $shopId, string $appUrl): array
    {
        return [
            'value' => $shopId,
            'app_url' => $appUrl,
        ];
    }
}
