<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233200RuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `rule_condition` (
              `id` BINARY(16) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `rule_id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16) NULL,
              `value` JSON NULL,
              `position` INT(11) DEFAULT 0 NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.rule_condition.value` CHECK (JSON_VALID (`value`)),
              CONSTRAINT `json.rule_condition.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.rule_condition.rule_id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.rule_condition.parent_id` FOREIGN KEY (`parent_id`)
                REFERENCES rule_condition (`id`) ON DELETE CASCADE ON UPDATE CASCADE
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
