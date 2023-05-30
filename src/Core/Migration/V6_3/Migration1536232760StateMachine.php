<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232760StateMachine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232760;
    }

    public function update(Connection $connection): void
    {
        $this->createStateMachineTable($connection);
        $this->createStateMachineStateTable($connection);
        $this->createStateMachineTransitionTable($connection);
        $this->createConstraints($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createStateMachineTable(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `state_machine` (
              `id`                  BINARY(16)                              NOT NULL,
              `technical_name`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `initial_state_id`    BINARY(16)                              NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.state_machine.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `state_machine_translation` (
              `language_id`         BINARY(16)                              NOT NULL,
              `state_machine_id`    BINARY(16)                              NOT NULL,
              `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`language_id`,`state_machine_id`),
              KEY `idx.state_machine_translation.language` (`language_id`),
              KEY `idx.state_machine_translation.state_machine` (`state_machine_id`),
              CONSTRAINT `json.state_machine_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeStatement($sql);
    }

    private function createStateMachineStateTable(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `state_machine_state` (
              `id`                  BINARY(16)                              NOT NULL,
              `technical_name`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `state_machine_id`    BINARY(16)                              NOT NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              KEY `idx.state_machine_state.state_machine_id` (`state_machine_id`),
              UNIQUE `uniq.technical_name_state_machine` (`technical_name`,`state_machine_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `state_machine_state_translation` (
              `language_id`             BINARY(16)                              NOT NULL,
              `state_machine_state_id`  BINARY(16)                              NOT NULL,
              `name`                    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `custom_fields`           JSON                                    NULL,
              `created_at`              DATETIME(3)                             NOT NULL,
              `updated_at`              DATETIME(3)                             NULL,
              PRIMARY KEY (`language_id`,`state_machine_state_id`),
              KEY `idx.language` (`language_id`),
              KEY `idx.state_machine` (`state_machine_state_id`),
              CONSTRAINT `json.state_machine_state_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeStatement($sql);
    }

    private function createStateMachineTransitionTable(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `state_machine_transition` (
              `id`                  BINARY(16)                              NOT NULL,
              `action_name`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `state_machine_id`    BINARY(16)                              NOT NULL,
              `from_state_id`       BINARY(16)                              NOT NULL,
              `to_state_id`         BINARY(16)                              NOT NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              KEY `idx.state_machine_transition.state_machine_id` (`state_machine_id`),
              KEY `idx.state_machine_transition.from_state_id` (`from_state_id`),
              KEY `idx.state_machine_transition.to_state_id` (`to_state_id`),
              UNIQUE `uniq.state_machine_transition.action_name_state_machine` (`action_name`,`state_machine_id`,`from_state_id`,`to_state_id`),
              CONSTRAINT `json.state_machine_transition.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeStatement($sql);
    }

    private function createConstraints(Connection $connection): void
    {
        $stateMachine = <<<'SQL'
            ALTER TABLE `state_machine`
              ADD CONSTRAINT `fk.state_machine.initial_state_id` FOREIGN KEY (`initial_state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL;

        $stateMachineTranslation = <<<'SQL'
            ALTER TABLE `state_machine_translation`
              ADD CONSTRAINT `fk.state_machine_translation.state_machine_id` FOREIGN KEY (`state_machine_id`)
                REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              ADD CONSTRAINT `fk.state_machine_translation.language_id`FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineState = <<<'SQL'
            ALTER TABLE `state_machine_state`
              ADD CONSTRAINT `fk.state_machine_state.state_machine_id` FOREIGN KEY (`state_machine_id`)
                REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineStateTranslation = <<<'SQL'
            ALTER TABLE `state_machine_state_translation`
              ADD CONSTRAINT `fk.state_machine_state_translation.state_machine_state_id` FOREIGN KEY (`state_machine_state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              ADD CONSTRAINT `fk.state_machine_state_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;

        $stateMachineTransition = <<<'SQL'
            ALTER TABLE `state_machine_transition`
              ADD CONSTRAINT `fk.state_machine_transition.state_machine_id` FOREIGN KEY (`state_machine_id`)
                REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              ADD CONSTRAINT `fk.state_machine_transition.to_state_id` FOREIGN KEY (`to_state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              ADD CONSTRAINT `fk.state_machine_transition.from_state_id` FOREIGN KEY (`from_state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;

        $connection->executeStatement($stateMachineTranslation);
        $connection->executeStatement($stateMachineState);
        $connection->executeStatement($stateMachineStateTranslation);
        $connection->executeStatement($stateMachineTransition);
        $connection->executeStatement($stateMachine);
    }
}
