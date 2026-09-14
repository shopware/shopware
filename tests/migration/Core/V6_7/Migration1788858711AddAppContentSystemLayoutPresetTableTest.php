<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1788858711AddAppContentSystemLayoutPresetTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1788858711AddAppContentSystemLayoutPresetTable::class)]
class Migration1788858711AddAppContentSystemLayoutPresetTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_content_system_layout_preset`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788858711, (new Migration1788858711AddAppContentSystemLayoutPresetTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_content_system_layout_preset'));

        $migration = new Migration1788858711AddAppContentSystemLayoutPresetTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_content_system_layout_preset'));

        $table = TableHelper::getTable($this->connection, 'app_content_system_layout_preset');

        static::assertCount(7, $table->columns);
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'app_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'schema'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'hash'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_layout_preset', 'updated_at'));
    }
}
