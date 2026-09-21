<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789983699AddProcessTransitionFromUnconfirmed extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789983699;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :technicalName',
            ['technicalName' => OrderTransactionStates::STATE_MACHINE],
        );

        if (!\is_string($stateMachineId)) {
            return;
        }

        $fromStateId = $this->getStateId($connection, $stateMachineId, OrderTransactionStates::STATE_UNCONFIRMED);
        $toStateId = $this->getStateId($connection, $stateMachineId, OrderTransactionStates::STATE_IN_PROGRESS);

        if ($fromStateId === null || $toStateId === null) {
            return;
        }

        $existingTransition = $connection->fetchOne(
            'SELECT `id` FROM `state_machine_transition` WHERE `state_machine_id` = :stateMachineId AND `from_state_id` = :fromStateId AND `to_state_id` = :toStateId AND `action_name` = :actionName',
            [
                'stateMachineId' => $stateMachineId,
                'fromStateId' => $fromStateId,
                'toStateId' => $toStateId,
                'actionName' => StateMachineTransitionActions::ACTION_PROCESS,
            ],
        );

        if ($existingTransition !== false) {
            return;
        }

        $connection->insert('state_machine_transition', [
            'id' => Uuid::randomBytes(),
            'state_machine_id' => $stateMachineId,
            'from_state_id' => $fromStateId,
            'to_state_id' => $toStateId,
            'action_name' => StateMachineTransitionActions::ACTION_PROCESS,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getStateId(Connection $connection, string $stateMachineId, string $technicalName): ?string
    {
        $stateId = $connection->fetchOne(
            'SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = :stateMachineId AND `technical_name` = :technicalName',
            [
                'stateMachineId' => $stateMachineId,
                'technicalName' => $technicalName,
            ],
        );

        return \is_string($stateId) ? $stateId : null;
    }
}
