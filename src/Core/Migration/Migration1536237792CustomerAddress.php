<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237792CustomerAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237792;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `customer_address` (
              `id` binary(16) NOT NULL,
              `customer_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `country_state_id` binary(16) NULL,
              `company` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `department` varchar(35) COLLATE utf8mb4_unicode_ci NULL,
              `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NULL,
              `title` varchar(100) COLLATE utf8mb4_unicode_ci NULL,
              `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
              `street` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
              `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci NULL,
              `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci NULL,
              `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`),
               CONSTRAINT `fk.customer_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.customer_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk.customer_address.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
