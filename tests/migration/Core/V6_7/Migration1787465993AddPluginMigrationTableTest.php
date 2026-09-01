<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1787465993AddPluginMigrationTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787465993AddPluginMigrationTable::class)]
class Migration1787465993AddPluginMigrationTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1787465993AddPluginMigrationTable();

        static::assertSame(1787465993, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1787465993AddPluginMigrationTable();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed(
            'SHOW COLUMNS FROM `plugin_migration`'
        );

        static::assertSame(
            ['plugin_name', 'migration_class', 'creation_timestamp', 'executed_at'],
            array_keys($columns)
        );

        static::assertSame('NO', $columns['plugin_name']['Null']);
        static::assertSame('NO', $columns['migration_class']['Null']);
        static::assertSame('NO', $columns['creation_timestamp']['Null']);
        static::assertSame('NO', $columns['executed_at']['Null']);

        $indexes = $this->connection->fetchAllAssociative('SHOW INDEXES FROM `plugin_migration`');

        $byName = [];
        foreach ($indexes as $index) {
            $byName[(string) $index['Key_name']][] = (string) $index['Column_name'];
        }

        static::assertSame(['plugin_name', 'migration_class'], $byName['PRIMARY']);
        static::assertSame(
            ['plugin_name', 'creation_timestamp'],
            $byName['uniq.plugin_migration.plugin_name__creation_timestamp']
        );
    }
}
