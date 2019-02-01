<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233000OrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_customer` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `customer_id` BINARY(16) NULL,
              `email` VARCHAR(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `first_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `salutation` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL,
              `title` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
              `customer_number` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.order_customer.customer_id` FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
