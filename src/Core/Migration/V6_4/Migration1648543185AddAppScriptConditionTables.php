<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1648543185AddAppScriptConditionTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648543185;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_script_condition` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `identifier` VARCHAR(255) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `group` VARCHAR(255) NULL,
                `script` LONGTEXT NULL,
                `constraints` LONGBLOB NULL,
                `config` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.app_script_condition.app_id` (`app_id`),
                CONSTRAINT `fk.app_script_condition.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_script_condition_translation` (
                `app_script_condition_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`app_script_condition_id`,`language_id`),
                KEY `fk.app_script_condition_translation.app_script_condition_id` (`app_script_condition_id`),
                KEY `fk.app_script_condition_translation.language_id` (`language_id`),
                CONSTRAINT `fk.app_script_condition_translation.app_script_condition_id` FOREIGN KEY (`app_script_condition_id`) REFERENCES `app_script_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.app_script_condition_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $columns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM `rule_condition`'), 'Field');

        if (!\in_array('script_id', $columns, true)) {
            $connection->executeStatement('ALTER TABLE `rule_condition` ADD `script_id` BINARY(16) NULL AFTER rule_id');
            $connection->executeStatement('ALTER TABLE `rule_condition` ADD CONSTRAINT `fk.rule_condition.script_id` FOREIGN KEY (`script_id`)
              REFERENCES `app_script_condition` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
