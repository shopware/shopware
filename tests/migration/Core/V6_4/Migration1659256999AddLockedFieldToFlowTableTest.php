<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659256999AddLockedFieldToFlowTable;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1659256999AddLockedFieldToFlowTable
 */
class Migration1659256999AddLockedFieldToFlowTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->prepare();

        $migration = new Migration1659256999AddLockedFieldToFlowTable();
        $migration->update($this->connection);

        // check if migration ran successfully
        $resultColumnExists = $this->hasColumn('flow', 'locked');

        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('flow', 'locked');

        if ($resultColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `flow` DROP COLUMN `locked`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}
