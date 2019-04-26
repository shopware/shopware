<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232780SnippetSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232780;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery(
            '
            CREATE TABLE IF NOT EXISTS `snippet_set` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `base_file` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `iso` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `attributes` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.snippet_set.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
