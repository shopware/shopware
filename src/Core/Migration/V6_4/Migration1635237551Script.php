<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1635237551Script extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1635237551;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `script` (
              `id` BINARY(16) NOT NULL,
              `script` LONGTEXT NOT NULL,
              `hook` VARCHAR(255) NOT NULL,
              `name` VARCHAR(1024) NOT NULL,
              `active` TINYINT(1) NOT NULL,
              `app_id` BINARY(16) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.app_script.hook` (`hook`),
              CONSTRAINT `fk.app_script.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
