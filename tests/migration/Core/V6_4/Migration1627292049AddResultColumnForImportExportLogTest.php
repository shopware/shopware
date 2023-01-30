<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1627292049AddResultColumnForImportExportLog;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1627292049AddResultColumnForImportExportLog
 */
class Migration1627292049AddResultColumnForImportExportLogTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1627292049AddResultColumnForImportExportLog $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1627292049AddResultColumnForImportExportLog();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        // check if migration ran successfully
        $resultColumnExists = $this->hasColumn('import_export_log', 'result');

        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('import_export_log', 'result');

        if ($resultColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `import_export_log` DROP COLUMN `result`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === $columnName
        )) > 0;
    }
}
