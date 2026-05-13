<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1753799632FixStateMachineHistoryIntegrationConstraint;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1753799632FixStateMachineHistoryIntegrationConstraint::class)]
class Migration1753799632FixStateMachineHistoryIntegrationConstraintTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1753799632, (new Migration1753799632FixStateMachineHistoryIntegrationConstraint())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropIntegrationForeignKey();

        $migration = new Migration1753799632FixStateMachineHistoryIntegrationConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKey = TableHelper::getForeignKeyOfTable($this->connection, StateMachineHistoryDefinition::ENTITY_NAME, 'fk.state_machine_history.integration_id');
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKey->onDeleteAction);
    }

    private function dropIntegrationForeignKey(): void
    {
        try {
            $this->connection->executeStatement('
                ALTER TABLE `state_machine_history` DROP FOREIGN KEY `fk.state_machine_history.integration_id`;
            ');
        } catch (\Throwable) {
        }
    }
}
