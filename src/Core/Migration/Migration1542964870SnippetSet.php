<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542964870SnippetSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542964870;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery(
            '
            CREATE TABLE IF NOT EXISTS `snippet_set` (
                `id` binary(16) NOT NULL,
                `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `base_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `iso` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            '
        );

        $connection->executeQuery(
            'ALTER TABLE snippet ADD `snippet_set_id` binary(16) NULL AFTER `value`;'
        );

        $connection->executeQuery(
            '
            ALTER TABLE `snippet` 
              ADD CONSTRAINT `fk.snippet.snippet_set_id`
              FOREIGN KEY(`snippet_set_id`)
              REFERENCES `snippet_set`(`id`)
            '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery(
            'ALTER TABLE snippet MODIFY COLUMN `snippet_set_id` binary(16) NOT NULL;'
        );
    }
}
