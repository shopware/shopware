<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Types\BinaryType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1746176773AddIntegrationIdStateHistory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1746176773AddIntegrationIdStateHistory::class)]
class Migration1746176773AddIntegrationIdStateHistoryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $this->revertMigration($connection);

        $migration = new Migration1746176773AddIntegrationIdStateHistory();
        $migration->update($connection);
        // Run twice to ensure idempotency
        $migration->update($connection);

        $manager = $connection->createSchemaManager();
        $columns = $manager->listTableColumns('state_machine_history');
        $foreignKeys = $manager->listTableForeignKeys('state_machine_history');

        $filteredForeignKeys = array_filter($foreignKeys, static fn (ForeignKeyConstraint $key) => $key->getName() === 'fk.state_machine_history.integration_id');
        $foreignKey = array_pop($filteredForeignKeys);

        static::assertNotNull($foreignKey);
        static::assertSame(['id'], $foreignKey->getForeignColumns());
        static::assertArrayHasKey('integration_id', $columns);
        static::assertInstanceOf(BinaryType::class, $columns['integration_id']->getType());
        static::assertSame(16, $columns['integration_id']->getLength());
        static::assertFalse($columns['integration_id']->getNotnull());
    }

    private function revertMigration(Connection $connection): void
    {
        if ($this->columnExists($connection, 'state_machine_history', 'integration_id')) {
            $connection->executeStatement('ALTER TABLE `state_machine_history` DROP FOREIGN KEY `fk.state_machine_history.integration_id`');
            $connection->executeStatement('ALTER TABLE `state_machine_history` DROP COLUMN `integration_id`');
        }
    }

    private function columnExists(Connection $connection, string $table, string $column): bool
    {
        return \array_key_exists(
            strtolower($column),
            $connection->createSchemaManager()->listTableColumns($table)
        );
    }
}
