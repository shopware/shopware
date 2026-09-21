<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788858711AddAppContentSystemLayoutPresetTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788858711;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_content_system_layout_preset` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `schema` JSON NOT NULL,
                `hash` VARCHAR(64) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.app_content_system_layout_preset.name` (`name`),
                KEY `fk.app_content_system_layout_preset.app_id` (`app_id`),
                CONSTRAINT `fk.app_content_system_layout_preset.app_id`
                    FOREIGN KEY (`app_id`) REFERENCES `app` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
