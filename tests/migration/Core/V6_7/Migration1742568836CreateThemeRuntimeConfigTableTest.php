<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1742568836CreateThemeRuntimeConfigTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1742568836CreateThemeRuntimeConfigTable::class)]
class Migration1742568836CreateThemeRuntimeConfigTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1742568836CreateThemeRuntimeConfigTable();
        static::assertSame(1742568836, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_runtime_config`;');

        static::assertFalse(TableHelper::tableExists($this->connection, 'theme_runtime_config'));

        $migration = new Migration1742568836CreateThemeRuntimeConfigTable();
        static::assertSame(1742568836, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);

        // check updated table
        static::assertTrue(TableHelper::tableExists($this->connection, 'theme_runtime_config'));

        $scriptFilesColumn = TableHelper::getColumnOfTable($this->connection, 'theme_runtime_config', 'script_files');
        static::assertFalse($scriptFilesColumn->isNotNull);

        static::assertTrue(TableHelper::indexExists($this->connection, 'theme_runtime_config', 'idx.technical_name'));
    }
}
