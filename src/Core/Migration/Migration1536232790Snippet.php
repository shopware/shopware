<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232790Snippet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232790;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `snippet` (
              `id` BINARY(16) NOT NULL,
              `translation_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `value` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
              `author` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `snippet_set_id` BINARY(16) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.snippet_set_id_translation_key` (`snippet_set_id`, `translation_key`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.snippet.snippet_set_id` FOREIGN KEY (`snippet_set_id`)
                REFERENCES `snippet_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
