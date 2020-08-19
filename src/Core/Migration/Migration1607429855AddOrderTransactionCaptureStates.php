<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1607429855AddOrderTransactionCaptureStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607429855;
    }

    public function update(Connection $connection): void
    {
        $this->addOrderTransactionCaptureStates($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addOrderTransactionCaptureStates(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();

        $pendingId = Uuid::randomBytes();
        $failedId = Uuid::randomBytes();
        $completedId = Uuid::randomBytes();

        $germanId = $this->fetchLanguageId('de-DE', $connection);
        $englishId = $this->fetchLanguageId('en-GB', $connection);

        $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderTransactionCaptureStates::STATE_MACHINE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($englishId) {
            $connection->insert('state_machine_translation', array_merge($translationEN, [
                'state_machine_id' => $stateMachineId,
                'name' => 'Capture state',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]));
        }
        if ($germanId) {
            $connection->insert('state_machine_translation', array_merge($translationDE, [
                'state_machine_id' => $stateMachineId,
                'name' => 'Erfassungsstatus',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]));
        }

        // states
        $connection->insert('state_machine_state', ['id' => $pendingId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionCaptureStates::STATE_PENDING, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $pendingId, 'name' => 'Pending']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $pendingId, 'name' => 'Ausstehend']));
        }

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionCaptureStates::STATE_COMPLETED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Completed']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        }

        $connection->insert('state_machine_state', ['id' => $failedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionCaptureStates::STATE_FAILED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($englishId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $failedId, 'name' => 'Failed']));
        }
        if ($germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $failedId, 'name' => 'Fehlgeschlagen']));
        }

        // transitions
        // from "pending" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_COMPLETE, 'from_state_id' => $pendingId, 'to_state_id' => $completedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_FAIL, 'from_state_id' => $pendingId, 'to_state_id' => $failedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $pendingId], ['id' => $stateMachineId]);
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
