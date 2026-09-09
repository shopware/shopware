<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788986059AppSeoUrlRoute extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788986059;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_seo_url_route` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `route_name` VARCHAR(255) NOT NULL,
                `hook` VARCHAR(255) NOT NULL,
                `entity_name` VARCHAR(64) NULL,
                `default_template` VARCHAR(750) NULL,
                `paths` JSON NULL,
                `label` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.app_seo_url_route.app_id` FOREIGN KEY (`app_id`)
                    REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `uniq.app_seo_url_route.app_id_name` UNIQUE (`app_id`, `name`),
                CONSTRAINT `uniq.app_seo_url_route.route_name` UNIQUE (`route_name`),
                CONSTRAINT `json.app_seo_url_route.paths` CHECK (JSON_VALID(`paths`)),
                CONSTRAINT `json.app_seo_url_route.label` CHECK (JSON_VALID(`label`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
