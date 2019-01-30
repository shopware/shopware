<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1548316507StateMachine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548316507;
    }

    public function update(Connection $connection): void
    {
        $this->createStateMachineTable($connection);
        $this->createStateMachineStateTable($connection);
        $this->createStateMachineTransitionTable($connection);
        $this->createConstraints($connection);

        $this->createOrderStateMachine($connection);
        $this->createOrderDeliveryStateMachine($connection);
        $this->createOrderTransactionStateMachine($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createStateMachineTable(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `state_machine` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `initial_state_id` binary(16) NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE `uniq.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `state_machine_translation` (
  `language_id` binary(16) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`language_id`,`state_machine_id`),
  KEY `idx.language` (`language_id`),
  KEY `idx.state_machine` (`state_machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);
    }

    private function createStateMachineStateTable(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `state_machine_state` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`id`),
  KEY `idx.state_machine_id` (`state_machine_id`),
  UNIQUE `uniq.technical_name_state_machine` (`technical_name`,`state_machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `state_machine_state_translation` (
  `language_id` binary(16) NOT NULL,
  `state_machine_state_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`language_id`,`state_machine_state_id`),
  KEY `idx.language` (`language_id`),
  KEY `idx.state_machine` (`state_machine_state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);
    }

    private function createStateMachineTransitionTable(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `state_machine_transition` (
  `id` binary(16) NOT NULL,
  `action_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `from_state_id` binary(16) NOT NULL,
  `to_state_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`id`),
  KEY `idx.state_machine_id` (`state_machine_id`),
  KEY `idx.from_state_id` (`from_state_id`),
  KEY `idx.to_state_id` (`to_state_id`),
  UNIQUE `uniq.action_name_state_machine` (`action_name`,`state_machine_id`,`from_state_id`,`to_state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    private function createConstraints(Connection $connection): void
    {
        $stateMachine = <<<SQL
ALTER TABLE `state_machine`
  ADD CONSTRAINT `fk.state_machine.initial_state_id` FOREIGN KEY (`initial_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL;

        $stateMachineTranslation = <<<SQL
ALTER TABLE `state_machine_translation`
  ADD CONSTRAINT `fk.state_machine_translation.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk.state_machine_translation.language_id`FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineState = <<<SQL
ALTER TABLE `state_machine_state`
  ADD CONSTRAINT `fk.state_machine_state.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineStateTranslation = <<<SQL
ALTER TABLE `state_machine_state_translation`
  ADD CONSTRAINT `fk.state_machine_state_translation.state_machine_state_id` FOREIGN KEY (`state_machine_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk.state_machine_state_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineTransition = <<<SQL
ALTER TABLE `state_machine_transition`
  ADD CONSTRAINT `fk.state_machine_transition.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk.state_machine_transition.to_state_id` FOREIGN KEY (`to_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk.state_machine_transition.from_state_id` FOREIGN KEY (`from_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;

        $connection->executeUpdate($stateMachineTranslation);
        $connection->executeUpdate($stateMachineState);
        $connection->executeUpdate($stateMachineStateTranslation);
        $connection->executeUpdate($stateMachineTransition);
        $connection->executeUpdate($stateMachine);
    }

    private function createOrderStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();
        $openId = Uuid::uuid4()->getBytes();
        $completedId = Uuid::uuid4()->getBytes();
        $inProgressId = Uuid::uuid4()->getBytes();
        $canceledId = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => Defaults::ORDER_STATE_MACHINE,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_COMPLETED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Done']));

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_IN_PROGRESS, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In progress']));

        $connection->insert('state_machine_state', ['id' => $canceledId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $canceledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $canceledId, 'name' => 'Cancelled']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'process', 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $inProgressId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'complete', 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $canceledId, 'to_state_id' => $openId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderDeliveryStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();
        $openId = Uuid::uuid4()->getBytes();
        $cancelledId = Uuid::uuid4()->getBytes();

        $shippedId = Uuid::uuid4()->getBytes();
        $shippedPartiallyId = Uuid::uuid4()->getBytes();

        $returnedId = Uuid::uuid4()->getBytes();
        $returnedPartiallyId = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => Defaults::ORDER_DELIVERY_STATE_MACHINE,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $shippedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_SHIPPED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedId, 'name' => 'Versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedId, 'name' => 'Shipped']));

        $connection->insert('state_machine_state', ['id' => $shippedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_PARTIALLY_SHIPPED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Teilweise versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Shipped (partially)']));

        $connection->insert('state_machine_state', ['id' => $returnedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_RETURNED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedId, 'name' => 'Retour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedId, 'name' => 'Returned']));

        $connection->insert('state_machine_state', ['id' => $returnedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_PARTIALLY_RETURNED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Teilretour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Returned (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_DELIVERY_STATES_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship_partially', 'from_state_id' => $openId, 'to_state_id' => $shippedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "shipped" to *
        // $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from shipped_partially
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderTransactionStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();

        $openId = Uuid::uuid4()->getBytes();
        $paidId = Uuid::uuid4()->getBytes();
        $paidPartiallyId = Uuid::uuid4()->getBytes();
        $cancelledId = Uuid::uuid4()->getBytes();
        $remindedId = Uuid::uuid4()->getBytes();
        $refundedId = Uuid::uuid4()->getBytes();
        $refundedPartiallyId = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => date(Defaults::DATE_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => date(Defaults::DATE_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => Defaults::ORDER_TRANSACTION_STATE_MACHINE,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Zahlungsstatus',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Payment state',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_OPEN, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $paidId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_PAID, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidId, 'name' => 'Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidId, 'name' => 'Paid']));

        $connection->insert('state_machine_state', ['id' => $paidPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_PARTIALLY_PAID, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Teilweise Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Paid (partially)']));

        $connection->insert('state_machine_state', ['id' => $refundedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_REFUNDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedId, 'name' => 'Erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedId, 'name' => 'Refunded']));

        $connection->insert('state_machine_state', ['id' => $refundedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_PARTIALLY_REFUNDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Teilweise Erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Refunded (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        $connection->insert('state_machine_state', ['id' => $remindedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_REMINDED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $remindedId, 'name' => 'Erinnert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $remindedId, 'name' => 'Reminded']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $openId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $openId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $openId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "reminded" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $remindedId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $remindedId, 'to_state_id' => $paidPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "paid_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $remindedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $paidId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "paid" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidId, 'to_state_id' => $refundedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // from "refunded_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $refundedPartiallyId, 'to_state_id' => $refundedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }
}
