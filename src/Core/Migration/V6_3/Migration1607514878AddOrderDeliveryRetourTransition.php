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
class Migration1607514878AddOrderDeliveryRetourTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607514878;
    }

    public function update(Connection $connection): void
    {
        $stateMachine = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :name', ['name' => 'order_delivery.state']);
        if (!$stateMachine) {
            return;
        }

        $returnedPartially = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned_partially', 'id' => $stateMachine]);

        if (!$returnedPartially) {
            return;
        }

        $returned = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => 'returned', 'id' => $stateMachine]);

        if (!$returned) {
            return;
        }

        $existedRetourTransition = $connection->fetchOne('
            SELECT `id` FROM `state_machine_transition`
            WHERE `action_name` = :actionName
            AND `state_machine_id` = :stateMachineId
            AND `from_state_id` = :fromStateId
            AND `to_state_id` = :toStateId;
        ', [
            'actionName' => 'retour',
            'stateMachineId' => $stateMachine,
            'fromStateId' => $returnedPartially,
            'toStateId' => $returned,
        ]);

        if ($existedRetourTransition) {
            return;
        }

        $connection->insert('state_machine_transition', [
            'id' => Uuid::randomBytes(),
            'action_name' => 'retour',
            'state_machine_id' => $stateMachine,
            'from_state_id' => $returnedPartially,
            'to_state_id' => $returned,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
