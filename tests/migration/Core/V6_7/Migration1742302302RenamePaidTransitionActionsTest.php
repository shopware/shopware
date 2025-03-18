<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1742302302RenamePaidTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1742302302RenamePaidTransitionActions::class)]
class Migration1742302302RenamePaidTransitionActionsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationUpdate(): void
    {
        $stateMachineId = Uuid::randomBytes();
        $fromStateId = Uuid::randomBytes();
        $toStateId = Uuid::randomBytes();

        $duplicateTransition1 = Uuid::randomBytes();
        $duplicateTransition2 = Uuid::randomBytes();
        $duplicateTransition3 = Uuid::randomBytes();
        $validTransition = Uuid::randomBytes();

        $this->connection->executeStatement('INSERT INTO `state_machine` (id, technical_name, created_at) VALUES (?, ?, ?)', [$stateMachineId, 'state_machine', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $this->connection->executeStatement('INSERT INTO `state_machine_state` (id, state_machine_id, technical_name, created_at) VALUES (?, ?, ?, ?)', [$fromStateId, $stateMachineId, 'from_state', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_state` (id, state_machine_id, technical_name, created_at) VALUES (?, ?, ?, ?)', [$toStateId, $stateMachineId, 'to_state', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // Insert duplicate transitions
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition1, $stateMachineId, $fromStateId, $toStateId, 'pay', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition2, $stateMachineId, $fromStateId, $toStateId, 'do_pay', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition3, $stateMachineId, $fromStateId, $toStateId, 'paid', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // Insert a random transition
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$validTransition, $stateMachineId, $fromStateId, $toStateId, 'other_action', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $migration = new Migration1742302302RenamePaidTransitionActions();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $remainingTransitions = $this->connection->fetchFirstColumn('SELECT id FROM `state_machine_transition` WHERE from_state_id = ? AND to_state_id = ?', [$fromStateId, $toStateId]);

        static::assertContains($duplicateTransition1, $remainingTransitions);
        static::assertContains($duplicateTransition2, $remainingTransitions);
        static::assertContains($duplicateTransition3, $remainingTransitions);

        static::assertContains($validTransition, $remainingTransitions);
    }

    public function testMigrationUpdateDestructive(): void
    {
        $stateMachineId = Uuid::randomBytes();
        $fromStateId = Uuid::randomBytes();
        $toStateId = Uuid::randomBytes();

        $duplicateTransition1 = Uuid::randomBytes();
        $duplicateTransition2 = Uuid::randomBytes();
        $duplicateTransition3 = Uuid::randomBytes();
        $validTransition = Uuid::randomBytes();

        $this->connection->executeStatement('INSERT INTO `state_machine` (id, technical_name, created_at) VALUES (?, ?, ?)', [$stateMachineId, 'state_machine', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $this->connection->executeStatement('INSERT INTO `state_machine_state` (id, state_machine_id, technical_name, created_at) VALUES (?, ?, ?, ?)', [$fromStateId, $stateMachineId, 'from_state', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_state` (id, state_machine_id, technical_name, created_at) VALUES (?, ?, ?, ?)', [$toStateId, $stateMachineId, 'to_state', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // Insert duplicate transitions
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition1, $stateMachineId, $fromStateId, $toStateId, 'pay', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition2, $stateMachineId, $fromStateId, $toStateId, 'do_pay', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$duplicateTransition3, $stateMachineId, $fromStateId, $toStateId, 'paid', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // Insert a random transition
        $this->connection->executeStatement('INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$validTransition, $stateMachineId, $fromStateId, $toStateId, 'other_action', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $migration = new Migration1742302302RenamePaidTransitionActions();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $remainingTransitions = $this->connection->fetchFirstColumn('SELECT id FROM `state_machine_transition` WHERE from_state_id = ? AND to_state_id = ?', [$fromStateId, $toStateId]);

        static::assertNotContains($duplicateTransition1, $remainingTransitions);
        static::assertNotContains($duplicateTransition2, $remainingTransitions);

        static::assertContains($duplicateTransition3, $remainingTransitions);
        static::assertContains($validTransition, $remainingTransitions);
    }
}
