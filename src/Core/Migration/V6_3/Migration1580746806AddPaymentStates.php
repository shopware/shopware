<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

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
#[Package('core')]
class Migration1580746806AddPaymentStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580746806;
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

        $stateRemindedId = $this->fetchStateId(OrderTransactionStates::STATE_REMINDED, $stateMachineId, $connection);

        $statePaidPartiallyId = $this->fetchStateId('paid_partially', $stateMachineId, $connection);

        $statePaidId = $this->fetchStateId('paid', $stateMachineId, $connection);

        $stateRefundedPartiallyId = $this->fetchStateId(
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            $stateMachineId,
            $connection
        );

        $stateCancelledId = $this->fetchStateId(OrderTransactionStates::STATE_CANCELLED, $stateMachineId, $connection);

        $stateInProgressId = Uuid::randomBytes();
        $stateFailedId = Uuid::randomBytes();

        $germanId = $this->fetchLanguageId('de-DE', $connection);

        $defaultLangId = $this->fetchLanguageId('en-GB', $connection);

        $translationEN = [];
        if ($defaultLangId !== $germanId) {
            $translationEN = ['language_id' => $defaultLangId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        }
        $translationDE = [];
        if ($germanId) {
            $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        }

        // states
        $connection->insert('state_machine_state', ['id' => $stateInProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_IN_PROGRESS, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        if ($defaultLangId !== $germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $stateInProgressId, 'name' => 'In Progress']));
        }

        if ($germanId) {
            $connection->insert(
                'state_machine_state_translation',
                array_merge(
                    $translationDE,
                    ['state_machine_state_id' => $stateInProgressId, 'name' => 'In Bearbeitung']
                )
            );
        }

        $connection->insert('state_machine_state', ['id' => $stateFailedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_FAILED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        if ($defaultLangId !== $germanId) {
            $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $stateFailedId, 'name' => 'Failed']));
        }

        if ($germanId) {
            $connection->insert(
                'state_machine_state_translation',
                array_merge($translationDE, ['state_machine_state_id' => $stateFailedId, 'name' => 'Fehlgeschlagen'])
            );
        }

        // transitions
        // from "in progress" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $stateInProgressId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'fail', 'from_state_id' => $stateInProgressId, 'to_state_id' => $stateFailedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_CANCEL, 'from_state_id' => $stateInProgressId, 'to_state_id' => $stateCancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid', 'from_state_id' => $stateInProgressId, 'to_state_id' => $statePaidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid_partially', 'from_state_id' => $stateInProgressId, 'to_state_id' => $statePaidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $stateInProgressId, 'to_state_id' => $statePaidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "failed" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $stateFailedId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'do_pay', 'from_state_id' => $stateFailedId, 'to_state_id' => $stateInProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $stateFailedId, 'to_state_id' => $statePaidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'fail', 'from_state_id' => $stateFailedId, 'to_state_id' => $stateFailedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid', 'from_state_id' => $stateFailedId, 'to_state_id' => $statePaidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'paid_partially', 'from_state_id' => $stateFailedId, 'to_state_id' => $statePaidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $stateFailedId, 'to_state_id' => $statePaidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'do_pay', 'from_state_id' => $stateOpenId, 'to_state_id' => $stateInProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'fail', 'from_state_id' => $stateOpenId, 'to_state_id' => $stateFailedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "reminded" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $stateRemindedId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'do_pay', 'from_state_id' => $stateRemindedId, 'to_state_id' => $stateInProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "paid_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $statePaidPartiallyId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'do_pay', 'from_state_id' => $statePaidPartiallyId, 'to_state_id' => $stateInProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "paid" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $statePaidId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "refunded_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => StateMachineTransitionActions::ACTION_REOPEN, 'from_state_id' => $stateRefundedPartiallyId, 'to_state_id' => $stateOpenId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if (!$langId && $code !== 'en-GB') {
            return null;
        }

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return (string) $langId;
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
