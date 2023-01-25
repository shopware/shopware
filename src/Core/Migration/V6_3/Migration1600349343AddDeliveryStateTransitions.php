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
class Migration1600349343AddDeliveryStateTransitions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600349343;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $this->fetchOrderDeliveryStateId($connection);
        $openStateId = $this->fetchOpenOrderDeliveryStateId($connection);
        $missingStates = $this->fetchMissingOrderDeliveryStates($connection, $stateMachineId);

        foreach ($missingStates as $missingState) {
            $connection->insert(
                'state_machine_transition',
                [
                    'id' => Uuid::randomBytes(),
                    'action_name' => 'reopen',
                    'state_machine_id' => $stateMachineId,
                    'from_state_id' => $missingState,
                    'to_state_id' => $openStateId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function fetchOrderDeliveryStateId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :technical_name', [
            'technical_name' => 'order_delivery.state',
        ]);
    }

    private function fetchOpenOrderDeliveryStateId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT initial_state_id FROM state_machine WHERE technical_name = :technical_name', [
            'technical_name' => 'order_delivery.state',
        ]);
    }

    /**
     * @return list<string>
     */
    private function fetchMissingOrderDeliveryStates(Connection $connection, string $stateMachineId): array
    {
        $allStates = $connection->fetchAllAssociative('SELECT action_name, from_state_id, to_state_id FROM state_machine_transition WHERE state_machine_id = :id', [
            'id' => $stateMachineId,
        ]);

        $reopenStates = array_filter($allStates, static fn (array $state) => $state['action_name'] === 'reopen');

        $missingStates = array_filter($allStates, static function (array $state) use ($reopenStates) {
            if (\in_array($state['to_state_id'], array_column($reopenStates, 'from_state_id'), true)) {
                return false;
            }

            if (\in_array($state['to_state_id'], array_column($reopenStates, 'to_state_id'), true)) {
                return false;
            }

            return true;
        });

        return array_unique(array_column($missingStates, 'to_state_id'));
    }
}
