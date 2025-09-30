<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1742568836CreateThemeRuntimeConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742568836;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `theme_runtime_config` (
                `theme_id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NULL,
                `resolved_config` JSON NOT NULL,
                `view_inheritance` JSON NOT NULL,
                `script_files` JSON NULL,
                `icon_sets` JSON NOT NULL,
                `updated_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`theme_id`),
                INDEX `idx.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }
}
