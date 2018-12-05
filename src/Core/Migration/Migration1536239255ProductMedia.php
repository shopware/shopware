<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239255ProductMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239255;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_media` (
              `id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `media_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.product_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_media.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_media.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            ALTER TABLE `product` ADD CONSTRAINT `fk.product.product_media_id` FOREIGN KEY (`product_media_id`, `product_media_version_id`) REFERENCES `product_media` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
