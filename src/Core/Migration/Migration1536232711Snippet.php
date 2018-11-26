<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232711Snippet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232711;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `snippet` (
              `id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `translation_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE `uniq.language_translation_key` (`language_id`, `translation_key`),
              CONSTRAINT `fk.snippet.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
