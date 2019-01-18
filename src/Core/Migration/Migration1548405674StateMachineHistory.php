<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548405674StateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548405674;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE `state_machine_history` (
  `id` binary(16) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `entity_class_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` json NOT NULL,
  `from_state_id` binary(16) NOT NULL,
  `to_state_id` binary(16) NOT NULL,
  `action_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` binary(16) NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.entity_id` CHECK (JSON_VALID(`entity_id`)),
  CONSTRAINT `fk.state_machine_history.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.from_state_id` FOREIGN KEY (`from_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.to_state_id`  FOREIGN KEY (`to_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
