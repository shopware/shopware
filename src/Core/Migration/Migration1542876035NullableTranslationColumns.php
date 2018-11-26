<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542876035NullableTranslationColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542876035;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
           ALTER TABLE `catalog_translation`
           MODIFY COLUMN `name` varchar(255) DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `category_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `configuration_group_option_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `configuration_group_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `country_state_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `country_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `currency_translation`
           MODIFY COLUMN `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `customer_group_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `discount_surcharge_translation`
           MODIFY COLUMN `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `listing_facet_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `listing_sorting_translation`
           MODIFY COLUMN `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `locale_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
           MODIFY COLUMN `territory` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `media_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `order_state_translation`
           MODIFY COLUMN `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `order_transaction_state_translation`
           MODIFY COLUMN `description` varchar(255) DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `payment_method_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `product_manufacturer_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `sales_channel_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `sales_channel_type_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `shipping_method_translation`
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');

        $connection->executeQuery('
           ALTER TABLE `unit_translation`
           MODIFY COLUMN `short_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
           MODIFY COLUMN `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
