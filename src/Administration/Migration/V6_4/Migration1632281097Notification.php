<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1632281097Notification extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1632281097;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `notification` (
                `id` BINARY(16) NOT NULL,
                `status` VARCHAR(255) NOT NULL,
                `message` VARCHAR(5000) NOT NULL,
                `admin_only` tinyint(1) NOT NULL DEFAULT 0,
                `required_privileges` json NULL,
                `created_by_integration_id` BINARY(16) NULL,
                `created_by_user_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.notification.created_by_integration_id` FOREIGN KEY (`created_by_integration_id`) REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.notification.created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
