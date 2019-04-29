<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552492072CustomerTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552492072;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `customer_tag` (
              `customer_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`customer_id`, `tag_id`),
              CONSTRAINT `fk.customer_tag.customer_id` FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`),
              CONSTRAINT `fk.customer_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
