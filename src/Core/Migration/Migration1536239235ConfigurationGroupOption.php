<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239235ConfigurationGroupOption extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239235;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `configuration_group_option` (
              `id` binary(16) NOT NULL,
              `configuration_group_id` binary(16) NOT NULL,
              `color_hex_code` VARCHAR(20) NULL,
              `media_id` binary(16) NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.configuration_group_option.configuration_group_id` FOREIGN KEY (`configuration_group_id`) REFERENCES `configuration_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.configuration_group_option.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
