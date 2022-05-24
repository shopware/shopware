<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1650981517RemoveShopwareId;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class Migration1650981517RemoveShopwareIdTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const KEY_SHOPWARE_ID = 'core.store.shopwareId';

    public function testMigrationRemovesSystemConfigKey(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $systemConfigService->set(self::KEY_SHOPWARE_ID, 'shopwareId');

        $connection = $this->getContainer()->get(Connection::class);

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
