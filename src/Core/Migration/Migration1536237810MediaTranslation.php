<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237810MediaTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237810;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `media_translation` (
              `media_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` text COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`media_id`, `language_id`),
              CONSTRAINT `fk.media_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.media_translation.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.media_translation.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
