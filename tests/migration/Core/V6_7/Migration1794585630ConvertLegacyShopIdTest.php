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

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeStatement('DELETE FROM `system_config` WHERE `configuration_key` IN (\'core.app.shopId\', \'core.app.shopIdV2\')');
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testTheConvertedShopIdKeepsTheIdAndMatchesTheShop(): void
    {
        $this->storeConfig('core.app.shopId', ['value' => 'legacyShopId', 'app_url' => (string) EnvironmentHelper::getVariable('APP_URL')]);

        $projectRoot = [$_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']];
        unset($_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']);
        try {
            (new Migration1794585630ConvertLegacyShopId())->update($this->connection);
            (new Migration1794585630ConvertLegacyShopId())->update($this->connection);
        } finally {
            [$_ENV['PROJECT_ROOT'], $_SERVER['PROJECT_ROOT']] = $projectRoot;
        }

        $shopIds = $this->fetchShopIds();
        static::assertCount(1, $shopIds);

        $shopId = ShopId::fromSystemConfig(json_decode($shopIds[0], true, flags: \JSON_THROW_ON_ERROR)['_value']);
        static::assertSame('legacyShopId', $shopId->id);
        static::assertTrue(self::getContainer()->get(FingerprintGenerator::class)->matchFingerprints($shopId->fingerprints)->isMatching());
    }

    public function testAnExistingShopIdIsKept(): void
    {
        $this->storeConfig('core.app.shopId', ['value' => 'legacyShopId', 'app_url' => 'https://old.example.com']);
        $this->storeConfig('core.app.shopIdV2', ['id' => 'currentShopId', 'fingerprints' => [], 'version' => 2]);

        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        $shopIds = $this->fetchShopIds();
        static::assertCount(1, $shopIds);
        static::assertSame('currentShopId', json_decode($shopIds[0], true, flags: \JSON_THROW_ON_ERROR)['_value']['id']);
    }

    public function testNoShopIdIsCreatedWithoutAnOldShopId(): void
    {
        (new Migration1794585630ConvertLegacyShopId())->update($this->connection);

        static::assertSame([], $this->fetchShopIds());
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
     * @return list<string>
     */
    private function fetchShopIds(): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = \'core.app.shopIdV2\'');
    }
}
