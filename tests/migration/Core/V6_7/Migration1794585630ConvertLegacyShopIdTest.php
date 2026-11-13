<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794585630ConvertLegacyShopId;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794585630ConvertLegacyShopId::class)]
class Migration1794585630ConvertLegacyShopIdTest extends TestCase
{
    use KernelTestBehaviour;

    private const LEGACY_SHOP_ID_KEY = 'core.app.shopId';
    private const SHOP_ID_KEY = 'core.app.shopIdV2';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` IN (?, ?)',
            [self::LEGACY_SHOP_ID_KEY, self::SHOP_ID_KEY]
        );
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testTheConvertedShopIdKeepsTheIdAndMatchesTheShop(): void
    {
        $this->storeConfig(self::LEGACY_SHOP_ID_KEY, ['value' => 'legacyShopId', 'app_url' => (string) EnvironmentHelper::getVariable('APP_URL')]);

        $migration = new Migration1794585630ConvertLegacyShopId();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $shopIds = $this->fetchConfigValues(self::SHOP_ID_KEY);
        static::assertCount(1, $shopIds);

        $shopId = ShopId::fromSystemConfig($shopIds[0]);
        static::assertSame('legacyShopId', $shopId->id);
        static::assertSame(2, $shopId->version);
        static::assertTrue(self::getContainer()->get(FingerprintGenerator::class)->matchFingerprints($shopId->fingerprints)->isMatching());
    }

    public function testTheInstallationPathMatchesTheShopWithoutProjectRoot(): void
    {
        $this->storeConfig(self::LEGACY_SHOP_ID_KEY, ['value' => 'legacyShopId', 'app_url' => 'https://old.example.com']);

        $projectRoot = [$_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']];
        unset($_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']);
        try {
            (new Migration1794585630ConvertLegacyShopId())->update($this->connection);
        } finally {
            [$_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']] = $projectRoot;
        }

        $shopId = ShopId::fromSystemConfig($this->fetchConfigValues(self::SHOP_ID_KEY)[0]);
        static::assertSame(self::getContainer()->getParameter('kernel.project_dir'), $shopId->getFingerprint('installation_path'));
    }

    public function testTheConvertedShopIdKeepsTheAppUrlOfTheOldShopId(): void
    {
        $this->storeConfig(self::LEGACY_SHOP_ID_KEY, ['value' => 'legacyShopId', 'app_url' => 'https://old.example.com']);

        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        $shopId = ShopId::fromSystemConfig($this->fetchConfigValues(self::SHOP_ID_KEY)[0]);
        static::assertSame('https://old.example.com', $shopId->getFingerprint('app_url'));
    }

    public function testAnExistingShopIdIsKept(): void
    {
        $existingShopId = ['id' => 'currentShopId', 'fingerprints' => ['app_url' => 'https://shop.example.com'], 'version' => 2];
        $this->storeConfig(self::LEGACY_SHOP_ID_KEY, ['value' => 'legacyShopId', 'app_url' => 'https://old.example.com']);
        $this->storeConfig(self::SHOP_ID_KEY, $existingShopId);

        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        static::assertSame([$existingShopId], $this->fetchConfigValues(self::SHOP_ID_KEY));
    }

    public function testAnUnreadableShopIdIsKept(): void
    {
        $this->storeConfig(self::LEGACY_SHOP_ID_KEY, ['value' => 'legacyShopId', 'app_url' => 'https://old.example.com']);
        $this->storeConfig(self::SHOP_ID_KEY, ['unexpected' => 'value']);

        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        static::assertSame([['unexpected' => 'value']], $this->fetchConfigValues(self::SHOP_ID_KEY));
    }

    public function testNoShopIdIsCreatedWithoutAnOldShopId(): void
    {
        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        static::assertSame([], $this->fetchConfigValues(self::SHOP_ID_KEY));
    }

    /**
     * @param array<string, mixed> $value
     */
    private function storeConfig(string $key, array $value): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchConfigValues(string $key): array
    {
        $values = $this->connection->fetchFirstColumn(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            [$key]
        );

        return array_map(static fn (string $value) => json_decode($value, true, flags: \JSON_THROW_ON_ERROR)['_value'], $values);
    }
}
