<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233260StateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233260;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
CREATE TABLE `state_machine_history` (
  `id` BINARY(16) NOT NULL,
  `state_machine_id` BINARY(16) NOT NULL,
  `entity_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` JSON NOT NULL,
  `from_state_id` BINARY(16) NOT NULL,
  `to_state_id` BINARY(16) NOT NULL,
  `action_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` BINARY(16) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.state_machine_history.entity_id` CHECK (JSON_VALID(`entity_id`)),
  CONSTRAINT `fk.state_machine_history.state_machine_id` FOREIGN KEY (`state_machine_id`)
    REFERENCES `state_machine` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.from_state_id` FOREIGN KEY (`from_state_id`)
    REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.to_state_id`  FOREIGN KEY (`to_state_id`)
    REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
