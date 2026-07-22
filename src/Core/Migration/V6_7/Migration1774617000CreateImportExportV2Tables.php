<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1774617000CreateImportExportV2Tables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774617000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_v2_profile` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `entity_name` VARCHAR(255) NOT NULL,
    `format` VARCHAR(64) NOT NULL,
    `identifier_paths` JSON NOT NULL,
    `payload_paths` JSON NOT NULL,
    `relation_modes` JSON NOT NULL,
    `field_mappings` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.import_export_v2_profile.name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_v2_artifact` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(255) NOT NULL,
    `contents` LONGTEXT NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_v2_run` (
    `id` BINARY(16) NOT NULL,
    `type` VARCHAR(64) NOT NULL,
    `profile_name` VARCHAR(255) NOT NULL,
    `state` VARCHAR(64) NOT NULL,
    `processed` INT NOT NULL,
    `succeeded` INT NOT NULL,
    `failed` INT NOT NULL,
    `failures` JSON NOT NULL,
    `cursor` JSON NOT NULL,
    `total_records` INT NULL,
    `last_error` VARCHAR(255) NULL,
    `processing_token` VARCHAR(32) NULL,
    `processing_expires_at` DATETIME(3) NULL,
    `input_artifact_id` VARCHAR(32) NULL,
    `output_artifact_id` VARCHAR(32) NULL,
    `record_ids` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        if (!$this->hasColumn($connection, 'import_export_v2_run', 'cursor')) {
            $connection->executeStatement('ALTER TABLE `import_export_v2_run` ADD `cursor` JSON NULL');
        }
        if (!$this->hasColumn($connection, 'import_export_v2_run', 'total_records')) {
            $connection->executeStatement('ALTER TABLE `import_export_v2_run` ADD `total_records` INT NULL');
        }
        if (!$this->hasColumn($connection, 'import_export_v2_run', 'last_error')) {
            $connection->executeStatement('ALTER TABLE `import_export_v2_run` ADD `last_error` VARCHAR(255) NULL');
        }
        if (!$this->hasColumn($connection, 'import_export_v2_run', 'processing_token')) {
            $connection->executeStatement('ALTER TABLE `import_export_v2_run` ADD `processing_token` VARCHAR(32) NULL');
        }
        if (!$this->hasColumn($connection, 'import_export_v2_run', 'processing_expires_at')) {
            $connection->executeStatement('ALTER TABLE `import_export_v2_run` ADD `processing_expires_at` DATETIME(3) NULL');
        }

        $connection->executeStatement(
            'UPDATE `import_export_v2_run`
            SET `cursor` = JSON_OBJECT(\'offset\', 0, \'chunkSize\', 100)
            WHERE JSON_TYPE(`cursor`) IS NULL OR `cursor` = \'null\''
        );
    }

    private function hasColumn(Connection $connection, string $table, string $column): bool
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column',
            [
                'table' => $table,
                'column' => $column,
            ]
        ) > 0;
    }
}
