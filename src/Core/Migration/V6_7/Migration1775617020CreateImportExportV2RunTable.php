<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('fundamentals@after-sales')]
class Migration1775617020CreateImportExportV2RunTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775617020;
    }

    public function update(Connection $connection): void
    {
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
    `offset` INT NOT NULL,
    `limit` INT NOT NULL,
    `next_byte_offset` INT NULL,
    `total_records` INT NULL,
    `export_filters` JSON NOT NULL,
    `file_id` VARCHAR(32) NOT NULL,
    `invalid_records_file_id` VARCHAR(32) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }
}
