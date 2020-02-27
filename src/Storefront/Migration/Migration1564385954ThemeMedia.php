<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1564385954ThemeMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1564385954;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `theme_media` (
              `theme_id` BINARY(16) NOT NULL,
              `media_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`theme_id`, `media_id`),
              CONSTRAINT `fk.theme_media.theme_id` FOREIGN KEY (`theme_id`)
                REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.theme_media.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
