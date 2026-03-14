<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773442200AddAppContentElementTypeTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773442200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_content_element_type` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `schema` JSON NOT NULL,
                `hash` VARCHAR(64) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.app_content_element_type.name_app` (`name`, `app_id`),
                KEY `fk.app_content_element_type.app_id` (`app_id`),
                KEY `idx.app_content_element_type.app_id_active` (`app_id`, `active`),
                CONSTRAINT `fk.app_content_element_type.app_id`
                    FOREIGN KEY (`app_id`) REFERENCES `app` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
