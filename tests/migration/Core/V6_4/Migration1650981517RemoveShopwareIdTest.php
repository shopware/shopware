<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1650981517RemoveShopwareId;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1650981517RemoveShopwareId
 */
class Migration1650981517RemoveShopwareIdTest extends TestCase
{
    use MigrationTestTrait;

    private const KEY_SHOPWARE_ID = 'core.store.shopwareId';

    public function testMigrationRemovesSystemConfigKey(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::KEY_SHOPWARE_ID,
            'configuration_value' => \json_encode(['_value' => 'shopwareId']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->assertConfigCount($connection, 1);

        $migration = new Migration1650981517RemoveShopwareId();

        $migration->updateDestructive($connection);

        $this->assertConfigCount($connection, 0);
    }

    private function assertConfigCount(Connection $connection, int $expected): void
    {
        $result = $connection->executeQuery(
            'SELECT count(*) from `system_config` WHERE `configuration_key` = :key',
            ['key' => self::KEY_SHOPWARE_ID],
        );

        $count = (int) $result->fetchOne();

        static::assertEquals($expected, $count);
    }
}
