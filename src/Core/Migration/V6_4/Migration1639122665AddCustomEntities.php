<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1639122665AddCustomEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639122665;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `custom_entity` (
              `id` binary(16) NOT NULL,
              `name` varchar(255) NOT NULL,
              `fields` json NOT NULL,
              `app_id` binary(16) DEFAULT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `app_id` (`app_id`),
              UNIQUE `name` (`name`),
              CONSTRAINT `fk.custom_entity.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.custom_entity.fields` CHECK (JSON_VALID(`fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
