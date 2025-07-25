<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1742302302RenamePaidTransitionActions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742302302;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :technicalName',
            ['technicalName' => OrderTransactionStates::STATE_MACHINE],
        );

        // create duplicate transitions, if they do not exist for:
        // pay -> paid
        // pay_partially -> paid_partially
        // do_pay -> process
        $query = <<<'SQL'
            INSERT INTO `state_machine_transition` (id, state_machine_id, from_state_id, to_state_id, action_name, custom_fields, created_at)
                SELECT UNHEX(REPLACE(UUID(), "-", "")), t.state_machine_id, t.from_state_id, t.to_state_id, :newActionName, t.custom_fields, :createdAt
                FROM `state_machine_transition` t
                LEFT JOIN `state_machine_transition` existing
                    ON t.state_machine_id = existing.state_machine_id
                    AND t.from_state_id = existing.from_state_id
                    AND t.to_state_id = existing.to_state_id
                    AND existing.action_name = :newActionName
                WHERE t.action_name = :oldActionName
                    AND existing.id IS NULL
                    AND t.state_machine_id = :stateMachineId
            SQL;

        $connection->executeStatement($query, [
            'id' => Uuid::randomBytes(),
            'newActionName' => 'paid',
            'oldActionName' => 'pay',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'stateMachineId' => $stateMachineId,
        ]);

        $connection->executeStatement($query, [
            'id' => Uuid::randomBytes(),
            'newActionName' => 'paid_partially',
            'oldActionName' => 'pay_partially',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'stateMachineId' => $stateMachineId,
        ]);

        $connection->executeStatement($query, [
            'id' => Uuid::randomBytes(),
            'newActionName' => 'process',
            'oldActionName' => 'do_pay',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'stateMachineId' => $stateMachineId,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :technicalName',
            ['technicalName' => OrderTransactionStates::STATE_MACHINE],
        );

        // remove old transition names
        $connection->delete('state_machine_transition', ['action_name' => 'pay', 'state_machine_id' => $stateMachineId]);
        $connection->delete('state_machine_transition', ['action_name' => 'pay_partially', 'state_machine_id' => $stateMachineId]);
        $connection->delete('state_machine_transition', ['action_name' => 'do_pay', 'state_machine_id' => $stateMachineId]);
    }
}
