<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552292082MediaTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552292082;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `media_tag` (
              `media_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`media_id`, `tag_id`),
              CONSTRAINT `fk.media_tag.id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`),
              CONSTRAINT `fk.media_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
