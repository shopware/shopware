<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234600SalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234600;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `sales_channel` (
              `id` binary(16) NOT NULL,
              `type_id` binary(16) NOT NULL,
              `configuration` JSON NULL,
              `access_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `language_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `active` tinyint(1) NOT NULL DEFAULT '1',
              `tax_calculation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE `uniq.access_key` (`access_key`),
              CONSTRAINT `json.configuration` CHECK (JSON_VALID(`configuration`)),
              CONSTRAINT `fk.sales_channel.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel.type_id` FOREIGN KEY (`type_id`) REFERENCES `sales_channel_type` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
