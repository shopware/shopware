<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1591361320ChargebackAndAuthorized;

class Migration1591361320ChargebackAndAuthorizedTest extends TestCase
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

        $migration = new Migration1591361320ChargebackAndAuthorized();
        $migration->update($this->connection);

        $transitions = $this->fetchTransitions();

        $expected = [
            ['action_name' => 'authorize',      'from_state' => 'in_progress',      'to_state' => 'authorized'],
            ['action_name' => 'authorize',      'from_state' => 'open',             'to_state' => 'authorized'],
            ['action_name' => 'authorize',      'from_state' => 'reminded',         'to_state' => 'authorized'],
            ['action_name' => 'cancel',         'from_state' => 'authorized',       'to_state' => 'cancelled'],
            ['action_name' => 'cancel',         'from_state' => 'chargeback',       'to_state' => 'cancelled'],
            ['action_name' => 'chargeback',     'from_state' => 'paid',             'to_state' => 'chargeback'],
            ['action_name' => 'chargeback',     'from_state' => 'paid_partially',   'to_state' => 'chargeback'],
            ['action_name' => 'fail',           'from_state' => 'authorized',       'to_state' => 'failed'],
            ['action_name' => 'paid',           'from_state' => 'authorized',       'to_state' => 'paid'],
            ['action_name' => 'paid',           'from_state' => 'chargeback',       'to_state' => 'paid'],
            ['action_name' => 'paid_partially', 'from_state' => 'authorized',       'to_state' => 'paid_partially'],
            ['action_name' => 'paid_partially', 'from_state' => 'chargeback',       'to_state' => 'paid_partially'],
        ];

        static::assertEquals($expected, $transitions);
    }

    public function testMigrationWithExistingStates(): void
    {
        $migration = new Migration1591361320ChargebackAndAuthorized();
        $migration->update($this->connection);

        $transitions = $this->fetchTransitions();

        $expected = [
            ['action_name' => 'authorize',      'from_state' => 'in_progress',      'to_state' => 'authorized'],
            ['action_name' => 'authorize',      'from_state' => 'open',             'to_state' => 'authorized'],
            ['action_name' => 'authorize',      'from_state' => 'reminded',         'to_state' => 'authorized'],
            ['action_name' => 'cancel',         'from_state' => 'authorized',       'to_state' => 'cancelled'],
            ['action_name' => 'cancel',         'from_state' => 'chargeback',       'to_state' => 'cancelled'],
            ['action_name' => 'chargeback',     'from_state' => 'paid',             'to_state' => 'chargeback'],
            ['action_name' => 'chargeback',     'from_state' => 'paid_partially',   'to_state' => 'chargeback'],
            ['action_name' => 'fail',           'from_state' => 'authorized',       'to_state' => 'failed'],
            ['action_name' => 'paid',           'from_state' => 'authorized',       'to_state' => 'paid'],
            ['action_name' => 'paid',           'from_state' => 'chargeback',       'to_state' => 'paid'],
            ['action_name' => 'paid_partially', 'from_state' => 'authorized',       'to_state' => 'paid_partially'],
            ['action_name' => 'paid_partially', 'from_state' => 'chargeback',       'to_state' => 'paid_partially'],
        ];

        static::assertEquals($expected, $transitions);
    }

    protected function rollbackMigrationChanges(): void
    {
        $this->connection->executeUpdate(
            "DELETE FROM state_machine_transition WHERE from_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'chargeback')"
        );
        $this->connection->executeUpdate(
            "DELETE FROM state_machine_transition WHERE to_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'chargeback')"
        );

        $this->connection->executeUpdate(
            "DELETE FROM state_machine_transition WHERE from_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'authorized')"
        );
        $this->connection->executeUpdate(
            "DELETE FROM state_machine_transition WHERE to_state_id = (SELECT id FROM state_machine_state WHERE technical_name = 'authorized')"
        );

        $this->connection->executeUpdate("DELETE FROM state_machine_state WHERE technical_name = 'chargeback'");
        $this->connection->executeUpdate("DELETE FROM state_machine_state WHERE technical_name = 'authorized'");
    }

    private function fetchTransitions()
    {
        return $this->connection->fetchAll("
SELECT trans.action_name, from_state.technical_name as from_state, to_state.technical_name as to_state
FROM state_machine_transition trans
	INNER JOIN state_machine_state from_state
		ON from_state.id = trans.from_state_id
	INNER JOIN state_machine_state to_state
		ON to_state.id = trans.to_state_id

WHERE trans.state_machine_id = (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1)
AND (
		from_state.technical_name IN ('chargeback', 'authorized')
	OR  to_state.technical_name  IN ('chargeback', 'authorized')
)
ORDER BY trans.action_name, from_state.technical_name, to_state.technical_name
;
        ");
    }
}
