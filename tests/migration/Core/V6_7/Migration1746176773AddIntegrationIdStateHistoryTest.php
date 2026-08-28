<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1746176773AddIntegrationIdStateHistory;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1746176773AddIntegrationIdStateHistory::class)]
class Migration1746176773AddIntegrationIdStateHistoryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1746176773, (new Migration1746176773AddIntegrationIdStateHistory())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $this->revertMigration($connection);

        $migration = new Migration1746176773AddIntegrationIdStateHistory();
        $migration->update($connection);
        // Run twice to ensure idempotency
        $migration->update($connection);

        $foreignKey = TableHelper::getForeignKeyOfTable($connection, 'state_machine_history', 'fk.state_machine_history.integration_id');
        static::assertSame('id', $foreignKey->referencedColumnNames[0]);

        $integrationIdColumn = TableHelper::getColumnOfTable($connection, 'state_machine_history', 'integration_id');
        static::assertSame(Types::BINARY, $integrationIdColumn->type);
        static::assertSame(16, $integrationIdColumn->length);
        static::assertFalse($integrationIdColumn->isNotNull);
    }

    private function revertMigration(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'state_machine_history', 'integration_id')) {
            $connection->executeStatement('ALTER TABLE `state_machine_history` DROP FOREIGN KEY `fk.state_machine_history.integration_id`');
            $connection->executeStatement('ALTER TABLE `state_machine_history` DROP COLUMN `integration_id`');
        }
    }
}
