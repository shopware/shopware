<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1615366708AddProductStreamMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615366708;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_stream_mapping` (
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `product_stream_id` binary(16) NOT NULL,
              PRIMARY KEY (`product_id`,`product_version_id`,`product_stream_id`),
              CONSTRAINT `fk.product_stream_mapping.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_stream_mapping.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
