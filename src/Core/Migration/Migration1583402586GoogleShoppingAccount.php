<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1583402586GoogleShoppingAccount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583402586;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `google_shopping_account` (
              `id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `credential` JSON NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `fk.google_shopping_account.sales_channel_id` (`sales_channel_id`),
              CONSTRAINT `fk.google_shopping_account.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE
              ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
