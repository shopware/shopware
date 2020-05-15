<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1588839674GoogleShoppingAdsAccount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1588839674;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `google_shopping_ads_account` (
                `id` BINARY(16) NOT NULL,
                `google_shopping_merchant_account_id` BINARY(16) NOT NULL,
                `ads_manager_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `ads_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.google_shopping_ads_account.merchant_account_id` (`google_shopping_merchant_account_id`),
                CONSTRAINT `json.google_shopping_ads_account.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.google_shopping_ads_account.merchant_account_id`
                    FOREIGN KEY (`google_shopping_merchant_account_id`)
                    REFERENCES `google_shopping_merchant_account` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
