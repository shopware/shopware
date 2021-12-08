<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1639416812AddNormalizedColumnToWebhookTable;

class Migration1639416812AddNormalizedColumnToWebhookTableTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Migration1639416812AddNormalizedColumnToWebhookTable $migration;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->migration = new Migration1639416812AddNormalizedColumnToWebhookTable();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        $normalizedColumnExists = $this->hasColumn('webhook', 'normalized');
        static::assertTrue($normalizedColumnExists);
    }

    private function prepare(): void
    {
        $normalizedColumnExists = $this->hasColumn('webhook', 'normalized');

        if ($normalizedColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `webhook` DROP COLUMN `normalized`');
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
