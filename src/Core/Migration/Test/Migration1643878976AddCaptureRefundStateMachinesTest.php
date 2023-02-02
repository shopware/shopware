<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1643878976AddCaptureRefundStateMachines;

/**
 * @internal
 */
#[Package('core')]
class Migration1643878976AddCaptureRefundStateMachinesTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->rollback();

        static::assertNull($this->getStateMachines());

        $this->execute();

        $stateMachines = $this->getStateMachines();

        static::assertNotNull($stateMachines);
        static::assertCount(2, $stateMachines);
        static::assertArrayHasKey(OrderTransactionCaptureStates::STATE_MACHINE, $stateMachines);
        static::assertArrayHasKey(OrderTransactionCaptureRefundStates::STATE_MACHINE, $stateMachines);

        $translations = $this->getTranslations($stateMachines);

        static::assertIsArray($translations);
        static::assertCount(4, $translations);

        $states = $this->getStates($stateMachines);

        static::assertIsArray($states);
        static::assertCount(8, $states);

        $transitions = $this->getTransitions($stateMachines);

        static::assertIsArray($transitions);
        static::assertCount(14, $transitions);
    }

    public function testMigrationTwice(): void
    {
        $this->rollback();

        static::assertNull($this->getStateMachines());

        $this->execute();
        $this->execute();

        $stateMachines = $this->getStateMachines();

        static::assertIsArray($stateMachines);
        static::assertCount(2, $stateMachines);
    }

    /**
     * @return array<string, string>|null
     */
    private function getStateMachines(): ?array
    {
        $stateMachines = $this->connection->fetchAllAssociative(
            '
            SELECT `id`, `technical_name` FROM `state_machine`
            WHERE `technical_name` = :capture
            OR `technical_name` = :captureRefund
        ',
            [
                'capture' => OrderTransactionCaptureStates::STATE_MACHINE,
                'captureRefund' => OrderTransactionCaptureRefundStates::STATE_MACHINE,
            ]
        );

        if (!$stateMachines) {
            return null;
        }

        $result = [];

        foreach ($stateMachines as $stateMachine) {
            $result[(string) $stateMachine['technical_name']] = (string) $stateMachine['id'];
        }

        return $result;
    }

    /**
     * @param array<string, string> $stateMachines
     *
     * @return list<string>|null
     */
    private function getTranslations(array $stateMachines): ?array
    {
        $translations = $this->connection->fetchFirstColumn(
            '
            SELECT `name` FROM `state_machine_translation`
            WHERE `state_machine_id` = :capture
            OR `state_machine_id` = :captureRefund
        ',
            [
                'capture' => $stateMachines[OrderTransactionCaptureStates::STATE_MACHINE],
                'captureRefund' => $stateMachines[OrderTransactionCaptureRefundStates::STATE_MACHINE],
            ]
        );

        return $translations ?: null;
    }

    /**
     * @param array<string, string> $stateMachines
     *
     * @return list<string>|null
     */
    private function getStates(array $stateMachines): ?array
    {
        $states = $this->connection->fetchFirstColumn(
            '
            SELECT `technical_name` FROM `state_machine_state`
            WHERE `state_machine_id` = :capture
            OR `state_machine_id` = :captureRefund
        ',
            [
                'capture' => $stateMachines[OrderTransactionCaptureStates::STATE_MACHINE],
                'captureRefund' => $stateMachines[OrderTransactionCaptureRefundStates::STATE_MACHINE],
            ]
        );

        return $states ?: null;
    }

    /**
     * @param array<string, string> $stateMachines
     *
     * @return list<string>|null
     */
    private function getTransitions(array $stateMachines): ?array
    {
        $transitions = $this->connection->fetchFirstColumn(
            '
            SELECT `action_name` FROM `state_machine_transition`
            WHERE `state_machine_id` = :capture
            OR `state_machine_id` = :captureRefund
        ',
            [
                'capture' => $stateMachines[OrderTransactionCaptureStates::STATE_MACHINE],
                'captureRefund' => $stateMachines[OrderTransactionCaptureRefundStates::STATE_MACHINE],
            ]
        );

        return $transitions ?: null;
    }

    private function execute(): void
    {
        (new Migration1643878976AddCaptureRefundStateMachines())->update($this->connection);
    }

    private function rollback(): void
    {
        $stateMachines = $this->getStateMachines();

        if (!$stateMachines) {
            return;
        }
        foreach ($stateMachines as $stateMachineId) {
            $this->connection->executeStatement(
                '
                DELETE FROM `state_machine_transition`
                WHERE `state_machine_id` = :stateMachineId
            ',
                ['stateMachineId' => $stateMachineId]
            );

            $this->connection->executeStatement(
                '
                DELETE FROM `state_machine_translation`
                WHERE `state_machine_id` = :stateMachineId
            ',
                ['stateMachineId' => $stateMachineId]
            );

            $this->connection->executeStatement(
                '
                DELETE FROM `state_machine_state`
                WHERE `state_machine_id` = :stateMachineId
            ',
                ['stateMachineId' => $stateMachineId]
            );

            $this->connection->executeStatement(
                '
                DELETE FROM `state_machine_translation`
                WHERE `state_machine_id` = :stateMachineId
            ',
                ['stateMachineId' => $stateMachineId]
            );

            $this->connection->executeStatement(
                '
                DELETE FROM `state_machine`
                WHERE `id` = :stateMachineId
            ',
                ['stateMachineId' => $stateMachineId]
            );
        }
    }
}
