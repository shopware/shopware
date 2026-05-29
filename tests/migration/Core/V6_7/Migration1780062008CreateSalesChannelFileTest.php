<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1780062008CreateSalesChannelFile;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780062008CreateSalesChannelFile::class)]
class Migration1780062008CreateSalesChannelFileTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780062008, (new Migration1780062008CreateSalesChannelFile())->getCreationTimestamp());
    }

    public function testMigrationCreatesTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'sales_channel_file'));

        (new Migration1780062008CreateSalesChannelFile())->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_file'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1780062008CreateSalesChannelFile();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_file'));
    }

    public function testTableHasExpectedColumns(): void
    {
        $this->rollback();

        (new Migration1780062008CreateSalesChannelFile())->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'sales_channel_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'file_family'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'file_name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'enabled'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'template_overrides'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_file', 'updated_at'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_file`');
    }
}
