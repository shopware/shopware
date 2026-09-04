<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773442200AddAppContentSystemElementTypeTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773442200AddAppContentSystemElementTypeTable::class)]
class Migration1773442200AddAppContentSystemElementTypeTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_content_system_element_type`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773442200, (new Migration1773442200AddAppContentSystemElementTypeTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_content_system_element_type'));

        $migration = new Migration1773442200AddAppContentSystemElementTypeTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_content_system_element_type'));

        $table = TableHelper::getTable($this->connection, 'app_content_system_element_type');

        static::assertCount(7, $table->columns);
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'app_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'schema'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'hash'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'updated_at'));

        static::assertFalse(TableHelper::columnExists($this->connection, 'app_content_system_element_type', 'active'), 'The active column was removed and must not be present');
    }
}
