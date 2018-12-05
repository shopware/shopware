<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240670RuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240670;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `rule_condition` ( 
              `id` binary(16) NOT NULL,
              `type` varchar(256) NOT NULL,
              `rule_id` binary(16) NOT NULL,
              `parent_id` binary(16) NULL,
              `value` JSON NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.value` CHECK (JSON_VALID (`value`)),
              CONSTRAINT `fk.condition_rule.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.condition_rule.parent_id` FOREIGN KEY (`parent_id`) REFERENCES rule_condition (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
