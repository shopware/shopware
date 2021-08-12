<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1628519513AddUnconfirmedTransactionState;

class Migration1628519513AddUnconfirmedTransactionStateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->rollbackMigrationChanges();

        $transitions = $this->fetchTransitions();
        static::assertEmpty($transitions);

        $migration = new Migration1628519513AddUnconfirmedTransactionState();
        $migration->update($this->connection);

        $existingTransitions = $this->fetchTransitions();
        foreach ($this->getExpectedTransitions() as $expectedTransition) {
            static::assertContains($expectedTransition, $existingTransitions);
        }
    }

    public function testMigrationWithExistingStates(): void
    {
        $migration = new Migration1628519513AddUnconfirmedTransactionState();
        $migration->update($this->connection);

        $existingTransitions = $this->fetchTransitions();
        foreach ($this->getExpectedTransitions() as $expectedTransition) {
            static::assertContains($expectedTransition, $existingTransitions);
        }
    }

    protected function rollbackMigrationChanges(): void
    {
        $this->connection->executeStatement(
            "DELETE FROM state_machine_transition WHERE from_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'unconfirmed')"
        );
        $this->connection->executeStatement(
            "DELETE FROM state_machine_transition WHERE to_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'unconfirmed')"
        );

        $this->connection->executeStatement("DELETE FROM state_machine_state WHERE technical_name = 'unconfirmed'");
    }

    private function fetchTransitions()
    {
        return $this->connection->fetchAllAssociative("
SELECT trans.action_name, from_state.technical_name as from_state, to_state.technical_name as to_state
FROM state_machine_transition trans
	INNER JOIN state_machine_state from_state
		ON from_state.id = trans.from_state_id
	INNER JOIN state_machine_state to_state
		ON to_state.id = trans.to_state_id

WHERE trans.state_machine_id = (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1)
AND (
		from_state.technical_name = 'unconfirmed'
	OR  to_state.technical_name = 'unconfirmed'
)
ORDER BY trans.action_name, from_state.technical_name, to_state.technical_name
;
        ");
    }

    /**
     * @return string[][]
     */
    private function getExpectedTransitions(): array
    {
        return [
            ['action_name' => 'authorize', 'from_state' => 'unconfirmed', 'to_state' => 'authorized'],
            ['action_name' => 'cancel', 'from_state' => 'unconfirmed', 'to_state' => 'cancelled'],
            ['action_name' => 'fail', 'from_state' => 'unconfirmed', 'to_state' => 'failed'],
            ['action_name' => 'paid', 'from_state' => 'unconfirmed', 'to_state' => 'paid'],
            ['action_name' => 'paid_partially', 'from_state' => 'unconfirmed', 'to_state' => 'paid_partially'],
            ['action_name' => 'process_unconfirmed', 'from_state' => 'cancelled', 'to_state' => 'unconfirmed'],
            ['action_name' => 'process_unconfirmed', 'from_state' => 'failed', 'to_state' => 'unconfirmed'],
            ['action_name' => 'process_unconfirmed', 'from_state' => 'open', 'to_state' => 'unconfirmed'],
            ['action_name' => 'process_unconfirmed', 'from_state' => 'paid_partially', 'to_state' => 'unconfirmed'],
            ['action_name' => 'process_unconfirmed', 'from_state' => 'reminded', 'to_state' => 'unconfirmed'],
            ['action_name' => 'reopen', 'from_state' => 'unconfirmed', 'to_state' => 'open'],
        ];
    }
}
