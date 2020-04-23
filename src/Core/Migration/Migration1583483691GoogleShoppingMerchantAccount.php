<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1583483691GoogleShoppingMerchantAccount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583483691;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `google_shopping_merchant_account` (
              `id` BINARY(16) NOT NULL,
              `google_shopping_account_id` BINARY(16) NOT NULL,
              `merchant_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `fk.google_shopping_merchant_account.google_shopping_account_id` (`google_shopping_account_id`),
              CONSTRAINT `fk.google_shopping_merchant_account.google_shopping_account_id` FOREIGN KEY (`google_shopping_account_id`) REFERENCES `google_shopping_account` (`id`) ON DELETE CASCADE
              ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
