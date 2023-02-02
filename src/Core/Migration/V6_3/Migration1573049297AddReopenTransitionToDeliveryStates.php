<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1573049297AddReopenTransitionToDeliveryStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1573049297;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = (string) $connection->fetchOne('
            SELECT id
            FROM state_machine
            WHERE technical_name = "order_delivery.state"
        ');

        $result = $connection->executeQuery('
            SELECT id, technical_name
            FROM state_machine_state
            WHERE state_machine_id = :stateMachineId AND (
                technical_name = "open" OR
                technical_name = "cancelled"
            )
        ', ['stateMachineId' => $stateMachineId])->fetchAllAssociative();

        $stateIds = [];
        foreach ($result as $row) {
            $stateIds[$row['technical_name']] = $row['id'];
        }

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $stateIds['cancelled'], 'to_state_id' => $stateIds['open'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
