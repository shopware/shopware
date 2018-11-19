<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1542612426StateMachine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542612426;
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
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `state_machine_state_id` (`initial_state_id`),
  UNIQUE `technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `state_machine_translation` (
  `language_id` binary(16) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`language_id`,`state_machine_id`),
  KEY `language` (`language_id`),
  KEY `state_machine` (`state_machine_id`)
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
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `state_machine_id` (`state_machine_id`),
  UNIQUE `technical_name_state_machine` (`technical_name`,`state_machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
CREATE TABLE `state_machine_state_translation` (
  `language_id` binary(16) NOT NULL,
  `state_machine_state_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`language_id`,`state_machine_state_id`),
  KEY `language` (`language_id`),
  KEY `state_machine` (`state_machine_state_id`)
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
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `state_machine_id` (`state_machine_id`),
  KEY `from_state_id` (`from_state_id`),
  KEY `to_state_id` (`to_state_id`),
  UNIQUE `action_name_state_machine` (`action_name`,`state_machine_id`,`from_state_id`,`to_state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    private function createConstraints(Connection $connection): void
    {
        $stateMachine = <<<SQL
ALTER TABLE `state_machine`
  ADD FOREIGN KEY (`initial_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL;

        $stateMachineTranslation = <<<SQL
ALTER TABLE `state_machine_translation`
  ADD FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineState = <<<SQL
ALTER TABLE `state_machine_state`
  ADD FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineStateTranslation = <<<SQL
ALTER TABLE `state_machine_state_translation`
  ADD FOREIGN KEY (`state_machine_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineTransition = <<<SQL
ALTER TABLE `state_machine_transition`
  ADD FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD FOREIGN KEY (`to_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD FOREIGN KEY (`from_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;

        $connection->executeUpdate($stateMachine);
        $connection->executeUpdate($stateMachineTranslation);
        $connection->executeUpdate($stateMachineState);
        $connection->executeUpdate($stateMachineStateTranslation);
        $connection->executeUpdate($stateMachineTransition);
    }

    private function createOrderDeliveryStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();
        $openId = Uuid::uuid4()->getBytes();
        $shippedId = Uuid::uuid4()->getBytes();
        $shippedPartiallyId = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_EN);

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

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship_partially', 'from_state_id' => $openId, 'to_state_id' => $shippedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $shippedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedId, 'to_state_id' => $openId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedId, 'to_state_id' => $shippedPartiallyId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();
        $openId = Uuid::uuid4()->getBytes();
        $completedId = Uuid::uuid4()->getBytes();
        $inProgressId = Uuid::uuid4()->getBytes();
        $canceledId = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_EN);

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
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Completed']));

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_IN_PROGRESS, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In progress']));

        $connection->insert('state_machine_state', ['id' => $canceledId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_STATE_STATES_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $canceledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $canceledId, 'name' => 'Cancelled']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'start', 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $inProgressId, 'to_state_id' => $canceledId, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'complete', 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderTransactionStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::uuid4()->getBytes();

        $openId = Uuid::uuid4()->getBytes();
        $completedId = Uuid::uuid4()->getBytes();
        $cancelledId = Uuid::uuid4()->getBytes();
        $partiallyInvoiced = Uuid::uuid4()->getBytes();
        $completelyInvoiced = Uuid::uuid4()->getBytes();
        $partiallyPaid = Uuid::uuid4()->getBytes();
        $completelyPayed = Uuid::uuid4()->getBytes();
        $firstReminder = Uuid::uuid4()->getBytes();
        $secondReminder = Uuid::uuid4()->getBytes();
        $thirdReminder = Uuid::uuid4()->getBytes();
        $encashment = Uuid::uuid4()->getBytes();
        $reserved = Uuid::uuid4()->getBytes();
        $delayed = Uuid::uuid4()->getBytes();
        $creditNotApproved = Uuid::uuid4()->getBytes();
        $creditPrelimininaryApproved = Uuid::uuid4()->getBytes();
        $creditApproved = Uuid::uuid4()->getBytes();
        $paymentOrdered = Uuid::uuid4()->getBytes();
        $timeExtensionRegistered = Uuid::uuid4()->getBytes();

        $germanId = Uuid::fromHexToBytes(Defaults::LANGUAGE_DE);
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_EN);

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

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_COMPLETED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Completed']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => Defaults::ORDER_TRANSACTION_STATES_CANCELLED, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        $connection->insert('state_machine_state', ['id' => $partiallyInvoiced, 'state_machine_id' => $stateMachineId, 'technical_name' => 'partially_invoced', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $partiallyInvoiced, 'name' => 'Teilweise in Rechnung gestellt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $partiallyInvoiced, 'name' => 'Partially invoiced']));

        $connection->insert('state_machine_state', ['id' => $completelyInvoiced, 'state_machine_id' => $stateMachineId, 'technical_name' => 'invoiced', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completelyInvoiced, 'name' => 'In Rechnung gestellt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completelyInvoiced, 'name' => 'Invoiced']));

        $connection->insert('state_machine_state', ['id' => $partiallyPaid, 'state_machine_id' => $stateMachineId, 'technical_name' => 'partially_paid', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $partiallyPaid, 'name' => 'Teilweise bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $partiallyPaid, 'name' => 'Partially paid']));

        $connection->insert('state_machine_state', ['id' => $completelyPayed, 'state_machine_id' => $stateMachineId, 'technical_name' => 'paid', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completelyPayed, 'name' => 'Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completelyPayed, 'name' => 'Paid']));

        $connection->insert('state_machine_state', ['id' => $firstReminder, 'state_machine_id' => $stateMachineId, 'technical_name' => 'first_reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $firstReminder, 'name' => '1. Erinnerung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $firstReminder, 'name' => '1st reminder']));

        $connection->insert('state_machine_state', ['id' => $secondReminder, 'state_machine_id' => $stateMachineId, 'technical_name' => 'second_reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $secondReminder, 'name' => '2. Erinnerung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $secondReminder, 'name' => '2nd reminder']));

        $connection->insert('state_machine_state', ['id' => $thirdReminder, 'state_machine_id' => $stateMachineId, 'technical_name' => 'third_reminder', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $thirdReminder, 'name' => '3. Erinnerung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $thirdReminder, 'name' => '3rd reminder']));

        $connection->insert('state_machine_state', ['id' => $encashment, 'state_machine_id' => $stateMachineId, 'technical_name' => 'encashment', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $encashment, 'name' => 'Einlösung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $encashment, 'name' => 'Encashment']));

        $connection->insert('state_machine_state', ['id' => $reserved, 'state_machine_id' => $stateMachineId, 'technical_name' => 'reserved', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $reserved, 'name' => 'Reserviert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $reserved, 'name' => 'Reserved']));

        $connection->insert('state_machine_state', ['id' => $delayed, 'state_machine_id' => $stateMachineId, 'technical_name' => 'delayed', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $delayed, 'name' => 'Verspätet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $delayed, 'name' => 'Delayed']));

        $connection->insert('state_machine_state', ['id' => $creditNotApproved, 'state_machine_id' => $stateMachineId, 'technical_name' => 'credit_not_approved', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $creditNotApproved, 'name' => 'Kredit nicht genehmigt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $creditNotApproved, 'name' => 'Credit not approved']));

        $connection->insert('state_machine_state', ['id' => $creditPrelimininaryApproved, 'state_machine_id' => $stateMachineId, 'technical_name' => 'credit_preliminary_approved', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $creditPrelimininaryApproved, 'name' => 'Kredit vorläufig genehmigt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $creditPrelimininaryApproved, 'name' => 'Credit preliminary approved']));

        $connection->insert('state_machine_state', ['id' => $creditApproved, 'state_machine_id' => $stateMachineId, 'technical_name' => 'credit_approved', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $creditApproved, 'name' => 'Kredit genehmigt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $creditApproved, 'name' => 'Credit approved']));

        $connection->insert('state_machine_state', ['id' => $paymentOrdered, 'state_machine_id' => $stateMachineId, 'technical_name' => 'payment_ordered', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paymentOrdered, 'name' => 'Zahlung angefordert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paymentOrdered, 'name' => 'Payment ordered']));

        $connection->insert('state_machine_state', ['id' => $timeExtensionRegistered, 'state_machine_id' => $stateMachineId, 'technical_name' => 'time_extension_registered', 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $timeExtensionRegistered, 'name' => 'Aufschub angefragt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $timeExtensionRegistered, 'name' => 'Time extension registered']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $openId, 'to_state_id' => $completelyPayed, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::uuid4()->getBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => date(Defaults::DATE_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }
}
