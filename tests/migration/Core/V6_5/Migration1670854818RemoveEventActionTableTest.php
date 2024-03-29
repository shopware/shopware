<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1670854818RemoveEventActionTable;

/**
 * @internal
 */
#[CoversClass(Migration1670854818RemoveEventActionTable::class)]
class Migration1670854818RemoveEventActionTableTest extends TestCase
{
    private const DELETED_TABLES = [
        'event_action_sales_channel',
        'event_action_rule',
        'event_action',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        foreach (self::DELETED_TABLES as $table) {
            $this->connection->executeStatement('CREATE TABLE `' . $table . '` (`id` BINARY(16) NOT NULL, PRIMARY KEY (`id`))');
        }
    }

    public function testMigrationDeletesOldTables(): void
    {
        $migration = new Migration1670854818RemoveEventActionTable();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->connection->fetchOne('SHOW TABLES LIKE "event_action%"'));
    }
}
