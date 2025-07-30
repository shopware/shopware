<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

        try {
            $this->connection->executeStatement('
                ALTER TABLE `state_machine_history` DROP FOREIGN KEY `fk.state_machine_history.integration_id`;
            ');
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        $migration = new Migration1753799632FixStateMachineHistoryIntegrationConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $fks = $this->connection->createSchemaManager()->listTableForeignKeys(StateMachineHistoryDefinition::ENTITY_NAME);
        $fk = current(array_filter($fks, fn (ForeignKeyConstraint $fk) => $fk->getName() === 'fk.state_machine_history.integration_id'));

        static::assertInstanceOf(ForeignKeyConstraint::class, $fk);
        static::assertSame(ReferentialAction::SET_NULL, $fk->getOnDeleteAction());
    }
}
