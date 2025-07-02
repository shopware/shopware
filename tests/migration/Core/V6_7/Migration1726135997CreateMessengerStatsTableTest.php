<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('messenger_stats');

        static::assertNotEmpty($columns);
        static::assertArrayHasKey('id', $columns);
        static::assertArrayHasKey('message_type', $columns);
        static::assertArrayHasKey('time_in_queue', $columns);
        static::assertArrayHasKey('created_at', $columns);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `messenger_stats`');
    }
}
