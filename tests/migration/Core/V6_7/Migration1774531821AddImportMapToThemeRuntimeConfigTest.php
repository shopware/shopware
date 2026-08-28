<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1774531821AddImportMapToThemeRuntimeConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1774531821AddImportMapToThemeRuntimeConfig::class)]
class Migration1774531821AddImportMapToThemeRuntimeConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1774531821AddImportMapToThemeRuntimeConfig();
        static::assertSame(1774531821, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'theme_runtime_config', 'import_map'));

        $migration = new Migration1774531821AddImportMapToThemeRuntimeConfig();

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'theme_runtime_config', 'import_map'));

        $column = TableHelper::getColumnOfTable($this->connection, 'theme_runtime_config', 'import_map');
        static::assertSame('json', $column->type);
        static::assertFalse($column->isNotNull);
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'theme_runtime_config', 'import_map')) {
            $this->connection->executeStatement('ALTER TABLE `theme_runtime_config` DROP COLUMN `import_map`');
        }
    }
}
