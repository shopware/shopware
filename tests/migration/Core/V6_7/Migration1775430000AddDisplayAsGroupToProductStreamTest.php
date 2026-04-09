<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1775430000AddDisplayAsGroupToProductStream;

/**
 * @internal
 */
#[CoversClass(Migration1775430000AddDisplayAsGroupToProductStream::class)]
class Migration1775430000AddDisplayAsGroupToProductStreamTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->rollback();

        parent::tearDown();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1775430000AddDisplayAsGroupToProductStream();

        static::assertSame(1775430000, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1775430000AddDisplayAsGroupToProductStream();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_stream', 'display_as_group'));

        $column = TableHelper::getColumnOfTable($this->connection, 'product_stream', 'display_as_group');
        static::assertSame('1', $column->defaultValue);
    }

    public function testMigrationDoesNotAlterExistingColumn(): void
    {
        $this->rollback();
        $this->connection->executeStatement('ALTER TABLE `product_stream` ADD COLUMN `display_as_group` TINYINT(1) NOT NULL DEFAULT 0;');

        $migration = new Migration1775430000AddDisplayAsGroupToProductStream();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_stream', 'display_as_group'));

        $column = TableHelper::getColumnOfTable($this->connection, 'product_stream', 'display_as_group');
        static::assertSame('0', $column->defaultValue);
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'product_stream', 'display_as_group')) {
            $this->connection->executeStatement('ALTER TABLE `product_stream` DROP COLUMN `display_as_group`;');
        }
    }
}
