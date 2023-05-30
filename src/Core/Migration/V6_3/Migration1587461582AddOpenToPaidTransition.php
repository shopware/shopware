<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1587461582AddOpenToPaidTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587461582;
    }

    public function update(Connection $connection): void
    {
        $this->addOrderTransactionStates($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addOrderTransactionStates(Connection $connection): void
    {
        $stateMachineId = (string) $connection->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :technical_name LIMIT 1',
            ['technical_name' => OrderTransactionStates::STATE_MACHINE]
        );

        $stateOpenId = $this->fetchStateId(OrderTransactionStates::STATE_OPEN, $stateMachineId, $connection);

        $statePaidPartiallyId = $this->fetchStateId('paid_partially', $stateMachineId, $connection);

        $statePaidId = $this->fetchStateId('paid', $stateMachineId, $connection);

        // from "open" to paid
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid', 'from_state_id' => $stateOpenId, 'to_state_id' => $statePaidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid_partially', 'from_state_id' => $stateOpenId, 'to_state_id' => $statePaidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function fetchStateId(string $technicalName, string $stateMachineId, Connection $connection): ?string
    {
        $stateId = $connection->fetchOne(
            'SELECT `id` FROM `state_machine_state` WHERE
            `technical_name` = :technical_name AND
            `state_machine_id` = :state_machine_id
            LIMIT 1',
            [
                'technical_name' => $technicalName,
                'state_machine_id' => $stateMachineId,
            ]
        );
        if ($stateId === false) {
            return null;
        }

        return (string) $stateId;
    }
}
