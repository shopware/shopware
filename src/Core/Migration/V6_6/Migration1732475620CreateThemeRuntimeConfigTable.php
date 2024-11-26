<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1732475620CreateThemeRuntimeConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732475620;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `theme_runtime_config` (
                `theme_id` BINARY(16) NOT NULL,
                `resolved_config` JSON NOT NULL,
                `script_files` JSON NOT NULL,
                `style_files` JSON NOT NULL,
                `icon_sets` JSON NOT NULL,
                `updated_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`theme_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }
}
