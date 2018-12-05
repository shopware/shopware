<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237801OrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237801;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_customer` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `customer_id` binary(16) NULL,
              `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NULL,
              `title` varchar(100) COLLATE utf8mb4_unicode_ci NULL,
              `customer_number` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.order.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
