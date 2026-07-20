<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1726135997CreateMessengerStatsTable;

/**
 * @internal
 */
#[CoversClass(Migration1726135997CreateMessengerStatsTable::class)]
class Migration1726135997CreateMessengerStatsTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1726135997, (new Migration1726135997CreateMessengerStatsTable())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();

        $migration = new Migration1726135997CreateMessengerStatsTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'message_type'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'time_in_queue'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'created_at'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `messenger_stats`');
    }
}
