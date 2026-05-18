<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('fundamentals@after-sales')]
class Migration1775617000CreateImportExportV2ProfileTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775617000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_v2_profile` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(255) NOT NULL,
    `entity` VARCHAR(255) NOT NULL,
    `format` VARCHAR(64) NOT NULL,
    `filters` JSON NOT NULL,
    `record_paths` JSON NOT NULL,
    `match_by` VARCHAR(255) NULL,
    `field_mappings` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.import_export_v2_profile.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }
}
