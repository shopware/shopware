<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1654839361ProductDownloadDelivery;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1654839361ProductDownloadDelivery
 */
class Migration1654839361ProductDownloadDeliveryTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1654839361ProductDownloadDelivery();
        $migration->update($this->connection);
        // test it can be executed multiple times
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM `delivery_time` WHERE `min` = 0 AND `max` = 0 AND `unit` = "hour"'));
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DELETE FROM `delivery_time` WHERE `min` = 0 AND `max` = 0 AND `unit` = "hour"');
    }
}
