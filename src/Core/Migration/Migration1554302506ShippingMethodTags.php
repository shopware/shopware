<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554302506ShippingMethodTags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554302506;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `shipping_method_tag` (
              `shipping_method_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`shipping_method_id`, `tag_id`),
              CONSTRAINT `fk.shipping_method_tag.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
