<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1642732351AddAppFlowActionId;

class Migration1642732351AddAppFlowActionIdTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Migration1642732351AddAppFlowActionId $migration;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->migration = new Migration1642732351AddAppFlowActionId();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        $appFlowActionIdColumnExists = $this->hasColumn('flow_sequence', 'app_flow_action_id');
        static::assertTrue($appFlowActionIdColumnExists);
    }

    private function prepare(): void
    {
        $appFlowActionIdColumnExists = $this->hasColumn('flow_sequence', 'app_flow_action_id');

        if ($appFlowActionIdColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `flow_sequence` DROP FOREIGN KEY `fk.flow_sequence.app_flow_action_id`');
            $this->connection->executeUpdate('ALTER TABLE `flow_sequence` DROP COLUMN `app_flow_action_id`, DROP INDEX `fk.flow_sequence.app_flow_action_id`');
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
