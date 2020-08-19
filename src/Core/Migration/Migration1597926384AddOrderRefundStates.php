<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1597926384AddOrderRefundStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597926384;
    }

    public function update(Connection $connection): void
    {
        $this->addOrderRefundStates($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addOrderRefundStates(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();

        $openId = Uuid::randomBytes();
        $inProgressId = Uuid::randomBytes();
        $cancelledId = Uuid::randomBytes();
        $failedId = Uuid::randomBytes();
        $completedId = Uuid::randomBytes();

        $germanId = $this->fetchLanguageId('de-DE', $connection);
        $englishId = $this->fetchLanguageId('en-GB', $connection);

        $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderRefundStates::STATE_MACHINE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($englishId) {
            $connection->insert('state_machine_translation', array_merge($translationEN, [
                'state_machine_id' => $stateMachineId,
                'name' => 'Refund state',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]));
        }
        if ($germanId) {
            $connection->insert('state_machine_translation', array_merge($translationDE, [
                'state_machine_id' => $stateMachineId,
                'name' => 'Erstattungsstatus',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]));
        }

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderRefundStates::STATE_OPEN, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        }

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderRefundStates::STATE_IN_PROGRESS, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In Progress']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        }

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderRefundStates::STATE_COMPLETED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Completed']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        }

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderRefundStates::STATE_CANCELLED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        }

        $connection->insert('state_machine_state', ['id' => $failedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderRefundStates::STATE_FAILED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $failedId, 'name' => 'Failed']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $failedId, 'name' => 'Fehlgeschlagen']));
        }

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_COMPLETE, 'from_state_id' => $openId, 'to_state_id' => $completedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_PROCESS, 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_CANCEL, 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_FAIL, 'from_state_id' => $openId, 'to_state_id' => $failedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "in_progress" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_COMPLETE, 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_CANCEL, 'from_state_id' => $inProgressId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_FAIL, 'from_state_id' => $inProgressId, 'to_state_id' => $failedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "completed" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $completedId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "cancelled" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $cancelledId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "failed" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $failedId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchColumn(
            'SELECT `language`.`id`
            FROM `language`
            INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id`
            WHERE `code` = :code
            LIMIT 1',
            ['code' => $code]
        );
        if ($langId === false) {
            return null;
        }

        return (string) $langId;
    }
}
