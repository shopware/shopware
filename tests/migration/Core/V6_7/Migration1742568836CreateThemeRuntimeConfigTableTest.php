<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

        $sm = $this->connection->createSchemaManager();
        static::assertFalse($sm->tablesExist(['theme_runtime_config']));

        $migration = new Migration1742568836CreateThemeRuntimeConfigTable();
        static::assertSame(1742568836, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);

        // check updated table
        static::assertTrue($sm->tablesExist(['theme_runtime_config']));

        $cols = $sm->listTableColumns('theme_runtime_config');
        static::assertCount(7, $cols);

        static::assertArrayHasKey('script_files', $cols);
        static::assertFalse($cols['script_files']->getNotnull());

        $indexes = $sm->listTableIndexes('theme_runtime_config');
        static::assertArrayHasKey('idx.technical_name', $indexes);
    }
}
