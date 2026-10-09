<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1783666808AddAppFeatureTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783666808;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_feature` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NULL,
                `app_name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `payload` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.app_feature.type` (`type`),
                CONSTRAINT `fk.app_feature.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `uniq.app_feature.app_name_type_name` UNIQUE (`app_name`, `type`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
