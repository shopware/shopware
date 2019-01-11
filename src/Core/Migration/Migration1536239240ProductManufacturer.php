<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239240ProductManufacturer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239240;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_manufacturer` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `link` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `media_id` binary(16) NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `version_id`),
               CONSTRAINT `fk.product_manufacturer.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
