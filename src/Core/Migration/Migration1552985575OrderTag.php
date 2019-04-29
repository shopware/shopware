<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552985575OrderTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552985575;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_tag` (
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`order_id`, `order_version_id`, `tag_id`),
              CONSTRAINT `fk.order_tag.order_tag__order_version_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
