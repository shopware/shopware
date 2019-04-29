<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552377321CategoryTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552377321;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `category_tag` (
              `category_id` BINARY(16) NOT NULL,
              `category_version_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`category_id`, `category_version_id`, `tag_id`),
              CONSTRAINT `fk.category_tag.category_tag__category_version_id` FOREIGN KEY (`category_id`, `category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
